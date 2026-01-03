<?php

namespace App\Http\Controllers;

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
    public function index(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        return view('admin.monitoring');
    }

    public function data(Request $request)
    {
        Gate::authorize('viewAdminDashboard');

        $now = Carbon::now()->toIso8601String();

        $metrics = [
            'generated_at' => $now,
            'queues' => $this->gatherQueueMetrics(),
            'tables' => $this->gatherTableCounts(),
            'failed_jobs' => $this->gatherFailedJobs(),
            'ingestion_activity' => $this->gatherIngestionActivity(),
            'env' => $this->gatherEnvironment(),
        ];

        return response()->json($metrics);
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
            // try to ping
            $redis->ping();
            $result['redis_available'] = true;

            // Discover queues by scanning keys if supported
            $keys = [];
            try {
                if (method_exists($redis, 'keys')) {
                    $keys = $redis->keys('queues:*') ?: [];
                }
            } catch (\Throwable $e) {
                // Fall back to configured list
                Log::debug('Redis keys scan failed for queue discovery', ['err' => $e->getMessage()]);
            }

            foreach ($keys as $k) {
                // keys may be bytes; cast to string
                $name = (string) $k;
                $parts = explode(':', $name);
                $q = end($parts);
                $queues[] = $q;
            }

            $queues = array_values(array_unique($queues));

            foreach ($queues as $q) {
                $key = "queues:{$q}";
                try {
                    $len = $redis->llen($key);
                } catch (\Throwable $e) {
                    $len = null;
                }

                $result['queues'][$q] = [
                    'depth' => is_int($len) ? $len : null,
                ];
            }

        } catch (\Throwable $e) {
            Log::warning('Redis not available for queue metrics', ['err' => $e->getMessage()]);
        }

        return $result;
    }

    protected function gatherTableCounts(): array
    {
        $tables = ['artists','albums','tracks','genres','artist_links','countries','data_source_queries','jobs','failed_jobs'];
        $out = [];

        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                $out[$t] = ['exists' => false, 'count' => null];
                continue;
            }

            $cacheKey = "admin_monitor:count:{$t}";
            $count = Cache::remember($cacheKey, 3, fn() => DB::table($t)->count());

            $out[$t] = ['exists' => true, 'count' => (int) $count];
        }

        return $out;
    }

    protected function gatherFailedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['exists' => false, 'count' => 0, 'recent' => []];
        }

        $count = DB::table('failed_jobs')->count();

        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id','queue','exception','failed_at'])
            ->map(fn($r) => [
                'id' => $r->id,
                'queue' => $r->queue,
                'failed_at' => $r->failed_at,
                'exception' => $this->summarizeException($r->exception),
            ])->values();

        return ['exists' => true, 'count' => (int) $count, 'recent' => $recent];
    }

    protected function gatherIngestionActivity(): array
    {
        $out = [];

        if (Schema::hasTable('data_source_queries')) {
            $rows = DB::table('data_source_queries')
                ->whereIn('data_source', ['wikidata','musicbrainz'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id','data_source','name','query','created_at'])
                ->groupBy('data_source');

            foreach (['wikidata','musicbrainz'] as $source) {
                $out[$source] = isset($rows[$source])
                    ? $rows[$source]->map(fn($r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'query' => $r->query,
                        'at' => $r->created_at,
                    ])->values()->toArray()
                    : [];
            }
        }

        return $out;
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
            'app_env' => env('APP_ENV'),
            'php_version' => PHP_VERSION,
            'git_commit' => $git,
            'queue_connection' => config('queue.default'),
            'cache_driver' => config('cache.default'),
        ];
    }

    protected function summarizeException(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        $line = strtok($text, "\n");
        return strlen($line) > 200 ? substr($line, 0, 197) . '...' : $line;
    }
}
