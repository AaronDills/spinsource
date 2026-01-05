<?php

namespace App\Http\Controllers;

use App\Models\JobHeartbeat;
use App\Models\JobRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class AdminMonitoringController extends Controller
{
    /**
     * Thresholds for warnings (in minutes or counts)
     */
    private const WARNING_QUEUE_DEPTH = 100;

    private const WARNING_NO_ACTIVITY_MINUTES = 30;

    private const WARNING_FAILED_JOBS = 10;

    private const WARNING_MISSING_TRACKLIST_PCT = 20; // Alert if >20% albums missing tracklists

    private const WARNING_MISSING_MBID_PCT = 30; // Alert if >30% missing MusicBrainz IDs

    private const WARNING_STALE_SYNC_HOURS = 192; // Alert if sync >8 days old (weekly + 1 day buffer)

    private const WARNING_ERROR_RATE_PCT = 5; // Alert if error rate >5% in last hour

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
            'coverage' => $this->gatherCoverageMetrics(),
            'sync_recency' => $this->gatherSyncRecency(),
            'error_rates' => $this->gatherErrorRates(),
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

        if (! Schema::hasTable('failed_jobs')) {
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
        $defaultConnection = config('queue.default');
        [$resolvedConnection, $driver] = $this->resolveQueueConnection($defaultConnection);

        $queues = ['default', 'wikidata', 'musicbrainz'];
        $configuredQueue = config("queue.connections.{$resolvedConnection}.queue");
        if ($configuredQueue) {
            $queues[] = $configuredQueue;
        }

        $result = [
            'connection' => $defaultConnection,
            'resolved_connection' => $resolvedConnection,
            'driver' => $driver,
            'redis_available' => $driver === 'redis' ? false : null,
            'driver_available' => true,
            'source' => $driver,
            'queues' => [],
        ];

        if ($driver === 'redis') {
            try {
                $redisConnection = config("queue.connections.{$resolvedConnection}.connection", 'default');
                $redis = Redis::connection($redisConnection);
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
                    if ($q && ! str_contains($q, ':notify') && ! str_contains($q, ':reserved')) {
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
                $result['driver_available'] = false;
                Log::warning('Redis not available for queue metrics', ['err' => $e->getMessage()]);
            }

            return $result;
        }

        if ($driver === 'database') {
            $queueTable = config("queue.connections.{$resolvedConnection}.table", 'jobs');
            $queueDbConnection = config("queue.connections.{$resolvedConnection}.connection");

            $schema = $queueDbConnection
                ? Schema::connection($queueDbConnection)
                : Schema::connection(config('database.default'));

            if (! $schema->hasTable($queueTable)) {
                $result['driver_available'] = false;

                return $result;
            }

            $db = $queueDbConnection ? DB::connection($queueDbConnection) : DB::connection();

            $counts = $db->table($queueTable)
                ->select('queue', DB::raw('count(*) as pending'))
                ->whereNull('reserved_at')
                ->groupBy('queue')
                ->pluck('pending', 'queue')
                ->toArray();

            $queues = array_values(array_unique(array_merge($queues, array_keys($counts))));

            foreach ($queues as $q) {
                $depth = (int) ($counts[$q] ?? 0);
                $result['queues'][$q] = [
                    'depth' => $depth,
                    'warning' => $depth > self::WARNING_QUEUE_DEPTH,
                ];
            }

            return $result;
        }

        $result['driver_available'] = false;

        return $result;
    }

    protected function resolveQueueConnection(string $defaultConnection): array
    {
        $driver = config("queue.connections.{$defaultConnection}.driver", 'unknown');

        if ($driver === 'failover') {
            $fallbacks = config("queue.connections.{$defaultConnection}.connections", []);
            foreach ($fallbacks as $connection) {
                $fallbackDriver = config("queue.connections.{$connection}.driver");
                if ($fallbackDriver) {
                    return [$connection, $fallbackDriver];
                }
            }
        }

        return [$defaultConnection, $driver];
    }

    protected function gatherTableCounts(): array
    {
        $tables = ['artists', 'albums', 'tracks', 'genres', 'artist_links', 'countries', 'data_source_queries', 'jobs', 'failed_jobs'];
        $out = [];

        foreach ($tables as $t) {
            if (! Schema::hasTable($t)) {
                $out[$t] = ['exists' => false, 'count' => null, 'delta' => null];

                continue;
            }

            $cacheKey = "admin_monitor:count:{$t}";
            $previousKey = "admin_monitor:prev_count:{$t}";

            $count = Cache::remember($cacheKey, 3, fn () => DB::table($t)->count());

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
        if (! Schema::hasTable('failed_jobs')) {
            return ['exists' => false, 'count' => 0, 'recent' => [], 'warning' => false];
        }

        $count = DB::table('failed_jobs')->count();

        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id', 'queue', 'exception', 'failed_at'])
            ->map(fn ($r) => [
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
        $sources = ['wikidata', 'musicbrainz'];

        $out = [
            'wikidata' => [],
            'musicbrainz' => [],
            'last_activity' => null,
            'minutes_since_activity' => null,
            'warning' => false,
        ];

        $lastActivity = null;
        $hasActivity = array_fill_keys($sources, false);

        if (Schema::hasTable('ingestion_events')) {
            $events = DB::table('ingestion_events')
                ->whereIn('source', $sources)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get(['id', 'source', 'name', 'context', 'created_at']);

            $grouped = $events->groupBy('source');

            foreach ($sources as $source) {
                $out[$source] = isset($grouped[$source])
                    ? $grouped[$source]
                        ->take(10)
                        ->map(fn ($r) => $this->formatIngestionEntry(
                            source: $source,
                            label: $r->name,
                            metric: null,
                            context: $r->context,
                            timestamp: $r->created_at,
                            id: $r->id,
                        ))
                        ->values()
                        ->toArray()
                    : [];

                if (! empty($out[$source])) {
                    $hasActivity[$source] = true;
                    $lastActivity = $this->maxTimestamp($lastActivity, $grouped[$source]->first()->created_at ?? null);
                }
            }
        }

        if (Schema::hasTable('job_heartbeats')) {
            $prefixes = [
                'wikidata' => ['Wikidata'],
                'musicbrainz' => ['MusicBrainz'],
            ];

            foreach ($sources as $source) {
                if ($hasActivity[$source]) {
                    continue; // Already populated from ingestion_events
                }

                $rows = DB::table('job_heartbeats')
                    ->where(function ($q) use ($prefixes, $source) {
                        foreach ($prefixes[$source] as $prefix) {
                            $q->orWhere('job', 'like', "{$prefix}%");
                        }
                    })
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get(['id', 'job', 'metric', 'context', 'created_at']);

                $out[$source] = $rows->map(fn ($r) => $this->formatIngestionEntry(
                    source: $source,
                    label: $r->job,
                    metric: $r->metric,
                    context: $r->context,
                    timestamp: $r->created_at,
                    id: $r->id,
                ))->toArray();

                if ($rows->isNotEmpty()) {
                    $hasActivity[$source] = true;
                    $lastActivity = $this->maxTimestamp($lastActivity, $rows->first()->created_at ?? null);
                }
            }
        }

        if ($lastActivity) {
            $lastTime = Carbon::parse($lastActivity);
            $out['last_activity'] = $lastTime->toIso8601String();
            $out['minutes_since_activity'] = (int) $lastTime->diffInMinutes(now());
            $out['warning'] = $out['minutes_since_activity'] > self::WARNING_NO_ACTIVITY_MINUTES;
        }

        return $out;
    }

    protected function formatIngestionEntry(
        string $source,
        ?string $label,
        ?string $metric,
        mixed $context,
        mixed $timestamp,
        mixed $id,
    ): array {
        $time = Carbon::parse($timestamp);
        $contextArray = $this->decodeContext($context);

        return [
            'id' => $id,
            'source' => $source,
            'label' => $label,
            'metric' => $metric,
            'context' => $contextArray,
            'job' => $label,
            'at' => $time->toIso8601String(),
            'at_human' => $time->diffForHumans(),
        ];
    }

    protected function decodeContext(mixed $context): array
    {
        if (is_array($context)) {
            return $context;
        }

        if (is_string($context)) {
            $decoded = json_decode($context, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function maxTimestamp(?Carbon $current, mixed $candidate): ?Carbon
    {
        if (! $candidate) {
            return $current;
        }

        $time = $candidate instanceof Carbon ? $candidate : Carbon::parse($candidate);

        if ($current === null || $time->gt($current)) {
            return $time;
        }

        return $current;
    }

    protected function gatherHeartbeats(): array
    {
        return [
            'runs' => JobHeartbeat::recentRuns(15),
            'summary' => JobHeartbeat::summary(60),
        ];
    }

    /**
     * Gather data coverage metrics.
     */
    protected function gatherCoverageMetrics(): array
    {
        $out = [
            'albums' => [],
            'artists' => [],
            'genres' => [],
        ];

        // Cache for 30 seconds since these are more expensive queries
        $cacheKey = 'admin_monitor:coverage';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Album coverage
        if (Schema::hasTable('albums')) {
            $totalAlbums = DB::table('albums')->count();
            $albumsWithMbid = DB::table('albums')->whereNotNull('musicbrainz_release_group_mbid')->count();
            $albumsWithTracklist = DB::table('albums')->whereNotNull('tracklist_fetched_at')->count();
            $albumsWithSpotify = DB::table('albums')->whereNotNull('spotify_album_id')->count();
            $albumsWithApple = DB::table('albums')->whereNotNull('apple_music_album_id')->count();
            $albumsWithCover = DB::table('albums')->whereNotNull('cover_image_commons')->count();

            $out['albums'] = [
                'total' => $totalAlbums,
                'with_mbid' => $albumsWithMbid,
                'with_mbid_pct' => $totalAlbums > 0 ? round(100 * $albumsWithMbid / $totalAlbums, 1) : 0,
                'missing_mbid' => $totalAlbums - $albumsWithMbid,
                'with_tracklist' => $albumsWithTracklist,
                'with_tracklist_pct' => $totalAlbums > 0 ? round(100 * $albumsWithTracklist / $totalAlbums, 1) : 0,
                'missing_tracklist' => $totalAlbums - $albumsWithTracklist,
                'missing_tracklist_pct' => $totalAlbums > 0 ? round(100 * ($totalAlbums - $albumsWithTracklist) / $totalAlbums, 1) : 0,
                'with_spotify' => $albumsWithSpotify,
                'with_spotify_pct' => $totalAlbums > 0 ? round(100 * $albumsWithSpotify / $totalAlbums, 1) : 0,
                'with_apple' => $albumsWithApple,
                'with_apple_pct' => $totalAlbums > 0 ? round(100 * $albumsWithApple / $totalAlbums, 1) : 0,
                'with_cover' => $albumsWithCover,
                'with_cover_pct' => $totalAlbums > 0 ? round(100 * $albumsWithCover / $totalAlbums, 1) : 0,
            ];
        }

        // Artist coverage
        if (Schema::hasTable('artists')) {
            $totalArtists = DB::table('artists')->count();
            $artistsWithMbid = DB::table('artists')->whereNotNull('musicbrainz_artist_mbid')->count();
            $artistsWithSpotify = DB::table('artists')->whereNotNull('spotify_artist_id')->count();
            $artistsWithApple = DB::table('artists')->whereNotNull('apple_music_artist_id')->count();
            $artistsWithDiscogs = DB::table('artists')->whereNotNull('discogs_artist_id')->count();
            $artistsWithWikipedia = DB::table('artists')->whereNotNull('wikipedia_url')->where('wikipedia_url', '!=', '')->count();

            $out['artists'] = [
                'total' => $totalArtists,
                'with_mbid' => $artistsWithMbid,
                'with_mbid_pct' => $totalArtists > 0 ? round(100 * $artistsWithMbid / $totalArtists, 1) : 0,
                'missing_mbid' => $totalArtists - $artistsWithMbid,
                'with_spotify' => $artistsWithSpotify,
                'with_spotify_pct' => $totalArtists > 0 ? round(100 * $artistsWithSpotify / $totalArtists, 1) : 0,
                'with_apple' => $artistsWithApple,
                'with_apple_pct' => $totalArtists > 0 ? round(100 * $artistsWithApple / $totalArtists, 1) : 0,
                'with_discogs' => $artistsWithDiscogs,
                'with_discogs_pct' => $totalArtists > 0 ? round(100 * $artistsWithDiscogs / $totalArtists, 1) : 0,
                'with_wikipedia' => $artistsWithWikipedia,
                'with_wikipedia_pct' => $totalArtists > 0 ? round(100 * $artistsWithWikipedia / $totalArtists, 1) : 0,
            ];
        }

        // Genre coverage
        if (Schema::hasTable('genres')) {
            $totalGenres = DB::table('genres')->count();
            $genresWithMbid = DB::table('genres')->whereNotNull('musicbrainz_id')->count();
            $genresWithParent = DB::table('genres')->whereNotNull('parent_genre_id')->count();

            $out['genres'] = [
                'total' => $totalGenres,
                'with_mbid' => $genresWithMbid,
                'with_mbid_pct' => $totalGenres > 0 ? round(100 * $genresWithMbid / $totalGenres, 1) : 0,
                'with_parent' => $genresWithParent,
                'with_parent_pct' => $totalGenres > 0 ? round(100 * $genresWithParent / $totalGenres, 1) : 0,
            ];
        }

        Cache::put($cacheKey, $out, 30);

        return $out;
    }

    /**
     * Gather sync recency information.
     */
    protected function gatherSyncRecency(): array
    {
        $scheduledJobs = [
            'wikidata:weekly-sync' => [
                'label' => 'Wikidata Weekly Sync',
                'schedule' => 'Sundays 2:00 AM',
                'jobs' => ['DiscoverNewGenres', 'DiscoverChangedGenres', 'DiscoverNewArtistIds', 'DiscoverChangedArtists', 'RefreshAlbumsForChangedArtists'],
            ],
            'musicbrainz:seed-tracklists' => [
                'label' => 'MusicBrainz Tracklist Sync',
                'schedule' => 'Daily 3:00 AM',
                'jobs' => ['MusicBrainzSeedTracklists', 'MusicBrainzFetchTracklist'],
            ],
            'search:weekly-rebuild' => [
                'label' => 'Search Index Rebuild',
                'schedule' => 'Sundays 6:00 AM',
                'jobs' => [],
            ],
        ];

        $out = [];

        if (! Schema::hasTable('job_runs')) {
            return ['available' => false, 'schedules' => []];
        }

        foreach ($scheduledJobs as $key => $config) {
            $lastRuns = [];
            $latestFinished = null;
            $totalProcessed = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalErrors = 0;

            // Get last successful run for each job in this schedule
            foreach ($config['jobs'] as $jobName) {
                $run = JobRun::lastSuccessful($jobName);
                if ($run) {
                    $lastRuns[$jobName] = [
                        'finished_at' => $run->finished_at?->toIso8601String(),
                        'finished_at_human' => $run->finished_at?->diffForHumans(),
                        'status' => $run->status,
                        'totals' => $run->totals,
                    ];

                    if (! $latestFinished || ($run->finished_at && $run->finished_at->gt($latestFinished))) {
                        $latestFinished = $run->finished_at;
                    }

                    $totalProcessed += $run->getTotal('processed');
                    $totalCreated += $run->getTotal('created');
                    $totalUpdated += $run->getTotal('updated');
                    $totalErrors += $run->getTotal('errors');
                }
            }

            $hoursSinceSync = $latestFinished ? $latestFinished->diffInHours(now()) : null;

            $out[$key] = [
                'label' => $config['label'],
                'schedule' => $config['schedule'],
                'last_finished_at' => $latestFinished?->toIso8601String(),
                'last_finished_at_human' => $latestFinished?->diffForHumans(),
                'hours_since_sync' => $hoursSinceSync,
                'warning' => $hoursSinceSync !== null && $hoursSinceSync > self::WARNING_STALE_SYNC_HOURS,
                'totals' => [
                    'processed' => $totalProcessed,
                    'created' => $totalCreated,
                    'updated' => $totalUpdated,
                    'errors' => $totalErrors,
                ],
                'jobs' => $lastRuns,
            ];
        }

        // Also get currently running jobs
        $runningJobs = JobRun::where('status', JobRun::STATUS_RUNNING)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get()
            ->map(fn ($run) => [
                'job_name' => $run->job_name,
                'started_at' => $run->started_at?->toIso8601String(),
                'started_at_human' => $run->started_at?->diffForHumans(),
                'totals' => $run->totals,
            ])
            ->toArray();

        return [
            'available' => true,
            'schedules' => $out,
            'running' => $runningJobs,
        ];
    }

    /**
     * Gather error rates per source.
     */
    protected function gatherErrorRates(): array
    {
        $out = [
            'available' => false,
            'sources' => [],
            'overall' => [],
        ];

        if (! Schema::hasTable('failed_jobs')) {
            return $out;
        }

        $out['available'] = true;

        // Get failure counts by queue in the last hour
        $lastHour = now()->subHour();
        $lastDay = now()->subDay();

        $hourlyFailures = DB::table('failed_jobs')
            ->where('failed_at', '>=', $lastHour)
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        $dailyFailures = DB::table('failed_jobs')
            ->where('failed_at', '>=', $lastDay)
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        // Get job completion counts from heartbeats if available
        $hourlyCompletions = [];
        $dailyCompletions = [];

        if (Schema::hasTable('job_heartbeats')) {
            $hourlyCompletions = DB::table('job_heartbeats')
                ->where('created_at', '>=', $lastHour)
                ->where('metric', 'completed')
                ->select('job', DB::raw('count(*) as count'))
                ->groupBy('job')
                ->pluck('count', 'job')
                ->toArray();

            $dailyCompletions = DB::table('job_heartbeats')
                ->where('created_at', '>=', $lastDay)
                ->where('metric', 'completed')
                ->select('job', DB::raw('count(*) as count'))
                ->groupBy('job')
                ->pluck('count', 'job')
                ->toArray();
        }

        foreach (['wikidata', 'musicbrainz', 'default'] as $queue) {
            $hourlyFail = $hourlyFailures[$queue] ?? 0;
            $dailyFail = $dailyFailures[$queue] ?? 0;

            // Estimate completions from heartbeats (rough approximation)
            $hourlyComplete = 0;
            $dailyComplete = 0;
            foreach ($hourlyCompletions as $job => $count) {
                if (str_contains(strtolower($job), $queue) || $queue === 'default') {
                    $hourlyComplete += $count;
                }
            }
            foreach ($dailyCompletions as $job => $count) {
                if (str_contains(strtolower($job), $queue) || $queue === 'default') {
                    $dailyComplete += $count;
                }
            }

            $hourlyTotal = $hourlyFail + $hourlyComplete;
            $dailyTotal = $dailyFail + $dailyComplete;

            $out['sources'][$queue] = [
                'hourly_failures' => $hourlyFail,
                'hourly_completions' => $hourlyComplete,
                'hourly_rate' => $hourlyTotal > 0 ? round(100 * $hourlyFail / $hourlyTotal, 1) : 0,
                'daily_failures' => $dailyFail,
                'daily_completions' => $dailyComplete,
                'daily_rate' => $dailyTotal > 0 ? round(100 * $dailyFail / $dailyTotal, 1) : 0,
                'warning' => $hourlyTotal > 0 && (100 * $hourlyFail / $hourlyTotal) > self::WARNING_ERROR_RATE_PCT,
            ];
        }

        // Overall stats
        $totalHourlyFail = array_sum($hourlyFailures);
        $totalDailyFail = array_sum($dailyFailures);
        $totalHourlyComplete = array_sum($hourlyCompletions);
        $totalDailyComplete = array_sum($dailyCompletions);

        $out['overall'] = [
            'hourly_failures' => $totalHourlyFail,
            'daily_failures' => $totalDailyFail,
            'hourly_rate' => ($totalHourlyFail + $totalHourlyComplete) > 0
                ? round(100 * $totalHourlyFail / ($totalHourlyFail + $totalHourlyComplete), 1)
                : 0,
            'daily_rate' => ($totalDailyFail + $totalDailyComplete) > 0
                ? round(100 * $totalDailyFail / ($totalDailyFail + $totalDailyComplete), 1)
                : 0,
        ];

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

        // Coverage warnings
        if (! empty($metrics['coverage']['albums'])) {
            $albums = $metrics['coverage']['albums'];
            if (($albums['missing_tracklist_pct'] ?? 0) > self::WARNING_MISSING_TRACKLIST_PCT) {
                $warnings[] = [
                    'type' => 'coverage',
                    'level' => 'warning',
                    'message' => "{$albums['missing_tracklist_pct']}% of albums missing tracklists ({$albums['missing_tracklist']} albums)",
                ];
            }
        }

        if (! empty($metrics['coverage']['artists'])) {
            $artists = $metrics['coverage']['artists'];
            $missingMbidPct = $artists['total'] > 0 ? round(100 * $artists['missing_mbid'] / $artists['total'], 1) : 0;
            if ($missingMbidPct > self::WARNING_MISSING_MBID_PCT) {
                $warnings[] = [
                    'type' => 'coverage',
                    'level' => 'info',
                    'message' => "{$missingMbidPct}% of artists missing MusicBrainz ID",
                ];
            }
        }

        // Sync recency warnings
        if (! empty($metrics['sync_recency']['schedules'])) {
            foreach ($metrics['sync_recency']['schedules'] as $key => $schedule) {
                if ($schedule['warning'] ?? false) {
                    $hours = $schedule['hours_since_sync'];
                    $days = round($hours / 24, 1);
                    $warnings[] = [
                        'type' => 'sync_stale',
                        'level' => 'warning',
                        'message' => "{$schedule['label']} hasn't run in {$days} days",
                    ];
                }
            }
        }

        // Error rate warnings
        if (! empty($metrics['error_rates']['sources'])) {
            foreach ($metrics['error_rates']['sources'] as $queue => $rates) {
                if ($rates['warning'] ?? false) {
                    $warnings[] = [
                        'type' => 'error_rate',
                        'level' => 'error',
                        'message' => "High error rate on '{$queue}' queue: {$rates['hourly_rate']}% in last hour",
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Format minutes into human-readable duration
     */
    protected function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' minute'.($minutes !== 1 ? 's' : '');
        }

        if ($minutes < 1440) { // Less than 24 hours
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            $result = $hours.' hour'.($hours !== 1 ? 's' : '');
            if ($mins > 0) {
                $result .= ' '.$mins.' min'.($mins !== 1 ? 's' : '');
            }

            return $result;
        }

        // Days and hours
        $days = intdiv($minutes, 1440);
        $remainingMins = $minutes % 1440;
        $hours = intdiv($remainingMins, 60);

        $result = $days.' day'.($days !== 1 ? 's' : '');
        if ($hours > 0) {
            $result .= ' '.$hours.' hour'.($hours !== 1 ? 's' : '');
        }

        return $result;
    }

    protected function summarizeException(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        $line = strtok($text, "\n");
        if ($line === false) {
            return null;
        }

        return strlen($line) > 200 ? substr($line, 0, 197).'...' : $line;
    }
}
