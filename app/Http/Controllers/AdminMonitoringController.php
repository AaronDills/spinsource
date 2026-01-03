<?php

namespace App\Http\Controllers;

use App\Models\JobHeartbeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class AdminMonitoringController extends Controller
{
    /**
     * Thresholds for warnings (in minutes or counts)
     */
    private const WARNING_QUEUE_DEPTH = 100;
    private const WARNING_NO_ACTIVITY_MINUTES = 30;
    private const WARNING_FAILED_JOBS = 10;

    public function index(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        return view('admin.monitoring');
    }

    public function data(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        $now = Carbon::now();

        $metrics = [
            'generated_at' => $now->toIso8601String(),
            'generated_at_human' => $now->format('H:i:s'),
            'queues' => $this->gatherQueueMetrics(),
            'tables' => $this->gatherTableCounts(),
            'failed_jobs' => $this->gatherFailedJobs(),
            'ingestion_activity' => $this->gatherIngestionActivity(),
            'heartbeats' => $this->gatherHeartbeats(),
            'env' => $this->gatherEnvironment(),
            'warnings' => [],
        ];

        // Generate warnings
        $metrics['warnings'] = $this->generateWarnings($metrics);

        return response()->json($metrics);
    }

    /**
     * Clear all failed jobs
     */
    public function clearFailedJobs(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        if (!Schema::hasTable('failed_jobs')) {
            return response()->json(['error' => 'Failed jobs table not found'], 404);
        }

        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();

        return response()->json([
            'success' => true,
            'cleared' => $count,
            'message' => "{$count} failed jobs cleared",
        ]);
    }

    protected function gatherQueueMetrics(): array
    {
        $queues = ['default', 'wikidata', 'musicbrainz'];

        $result = [
            'connection' => config('queue.default'),
            'driver' => config('queue.connections.' . config('queue.default') . '.driver', 'unknown'),
            'redis_available' => false,
            'queues' => [],
        ];

        try {
            $redis = Redis::connection();
            $redis->ping();
            $result['redis_available'] = true;

            // Discover queues by scanning keys if supported
            $keys = [];
            try {
                if (method_exists($redis, 'keys')) {
                    $keys = $redis->keys('queues:*') ?: [];
                }
            } catch (\Throwable $e) {
                Log::debug('Redis keys scan failed for queue discovery', ['err' => $e->getMessage()]);
            }

            foreach ($keys as $k) {
                $name = (string) $k;
                $parts = explode(':', $name);
                $q = end($parts);
                if ($q && !str_contains($q, ':notify') && !str_contains($q, ':reserved')) {
                    $queues[] = $q;
                }
            }

            $queues = array_values(array_unique($queues));

            foreach ($queues as $q) {
                $key = "queues:{$q}";
                try {
                    $len = $redis->llen($key);
                } catch (\Throwable $e) {
                    $len = null;
                }

                $depth = is_int($len) ? $len : 0;
                $result['queues'][$q] = [
                    'depth' => $depth,
                    'warning' => $depth > self::WARNING_QUEUE_DEPTH,
                ];
            }

        } catch (\Throwable $e) {
            Log::warning('Redis not available for queue metrics', ['err' => $e->getMessage()]);
        }

        return $result;
    }

    protected function gatherTableCounts(): array
    {
        $tables = ['artists', 'albums', 'tracks', 'genres', 'artist_links', 'countries', 'data_source_queries', 'jobs', 'failed_jobs'];
        $out = [];

        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) {
                $out[$t] = ['exists' => false, 'count' => null, 'delta' => null];
                continue;
            }

            $cacheKey = "admin_monitor:count:{$t}";
            $previousKey = "admin_monitor:prev_count:{$t}";

            $count = Cache::remember($cacheKey, 3, fn() => DB::table($t)->count());

            // Track delta from previous reading
            $previous = Cache::get($previousKey);
            $delta = null;
            if ($previous !== null) {
                $delta = $count - $previous;
            }

            // Store current as previous for next comparison (store for 60 seconds)
            Cache::put($previousKey, $count, 60);

            $out[$t] = [
                'exists' => true,
                'count' => (int) $count,
                'delta' => $delta,
                'formatted' => number_format($count),
            ];
        }

        return $out;
    }

    protected function gatherFailedJobs(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return ['exists' => false, 'count' => 0, 'recent' => [], 'warning' => false];
        }

        $count = DB::table('failed_jobs')->count();

        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id', 'queue', 'exception', 'failed_at'])
            ->map(fn($r) => [
                'id' => $r->id,
                'queue' => $r->queue,
                'failed_at' => $r->failed_at,
                'failed_at_human' => Carbon::parse($r->failed_at)->diffForHumans(),
                'exception' => $this->summarizeException($r->exception),
            ])->values()->toArray();

        return [
            'exists' => true,
            'count' => (int) $count,
            'recent' => $recent,
            'warning' => $count > self::WARNING_FAILED_JOBS,
        ];
    }

    protected function gatherIngestionActivity(): array
    {
        $out = [
            'wikidata' => [],
            'musicbrainz' => [],
            'last_activity' => null,
            'minutes_since_activity' => null,
            'warning' => false,
        ];

        if (Schema::hasTable('data_source_queries')) {
            $rows = DB::table('data_source_queries')
                ->whereIn('data_source', ['wikidata', 'musicbrainz'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'data_source', 'name', 'query', 'created_at']);

            $grouped = $rows->groupBy('data_source');

            foreach (['wikidata', 'musicbrainz'] as $source) {
                $out[$source] = isset($grouped[$source])
                    ? $grouped[$source]->take(10)->map(fn($r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'query' => $r->query,
                        'at' => $r->created_at,
                        'at_human' => Carbon::parse($r->created_at)->diffForHumans(),
                    ])->values()->toArray()
                    : [];
            }

            // Find most recent activity
            $lastRow = $rows->first();
            if ($lastRow) {
                $lastTime = Carbon::parse($lastRow->created_at);
                $out['last_activity'] = $lastTime->toIso8601String();
                $out['minutes_since_activity'] = (int) $lastTime->diffInMinutes(now());
                $out['warning'] = $out['minutes_since_activity'] > self::WARNING_NO_ACTIVITY_MINUTES;
            }
        }

        return $out;
    }

    protected function gatherHeartbeats(): array
    {
        return [
            'runs' => JobHeartbeat::recentRuns(15),
            'summary' => JobHeartbeat::summary(60),
        ];
    }

    protected function gatherEnvironment(): array
    {
        $git = null;
        try {
            if (file_exists(base_path('.git/HEAD'))) {
                $commit = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?? '') ?: null;
                $git = $commit;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'git_commit' => $git,
            'queue_connection' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'db_connection' => config('database.default'),
        ];
    }

    protected function generateWarnings(array $metrics): array
    {
        $warnings = [];

        // Queue depth warnings
        if ($metrics['queues']['redis_available']) {
            foreach ($metrics['queues']['queues'] as $name => $queue) {
                if (($queue['depth'] ?? 0) > self::WARNING_QUEUE_DEPTH) {
                    $warnings[] = [
                        'type' => 'queue_depth',
                        'level' => 'warning',
                        'message' => "Queue '{$name}' has {$queue['depth']} pending jobs",
                    ];
                }
            }
        } else {
            $warnings[] = [
                'type' => 'redis',
                'level' => 'error',
                'message' => 'Redis is not available - queue monitoring disabled',
            ];
        }

        // Failed jobs warning
        if ($metrics['failed_jobs']['count'] > 0) {
            $level = $metrics['failed_jobs']['count'] > self::WARNING_FAILED_JOBS ? 'error' : 'warning';
            $warnings[] = [
                'type' => 'failed_jobs',
                'level' => $level,
                'message' => "{$metrics['failed_jobs']['count']} failed jobs in queue",
            ];
        }

        // Ingestion activity warning
        if ($metrics['ingestion_activity']['warning']) {
            $mins = $metrics['ingestion_activity']['minutes_since_activity'];
            $duration = $this->formatDuration($mins);
            $warnings[] = [
                'type' => 'ingestion',
                'level' => 'warning',
                'message' => "No ingestion activity for {$duration}",
            ];
        }

        return $warnings;
    }

    /**
     * Format minutes into human-readable duration
     */
    protected function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }

        if ($minutes < 1440) { // Less than 24 hours
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            $result = $hours . ' hour' . ($hours !== 1 ? 's' : '');
            if ($mins > 0) {
                $result .= ' ' . $mins . ' min' . ($mins !== 1 ? 's' : '');
            }
            return $result;
        }

        // Days and hours
        $days = intdiv($minutes, 1440);
        $remainingMins = $minutes % 1440;
        $hours = intdiv($remainingMins, 60);

        $result = $days . ' day' . ($days !== 1 ? 's' : '');
        if ($hours > 0) {
            $result .= ' ' . $hours . ' hour' . ($hours !== 1 ? 's' : '');
        }

        return $result;
    }

    protected function summarizeException(?string $text): ?string
    {
        if (!$text) {
            return null;
        }

        $line = strtok($text, "\n");
        if ($line === false) {
            return null;
        }
        return strlen($line) > 200 ? substr($line, 0, 197) . '...' : $line;
    }
}
