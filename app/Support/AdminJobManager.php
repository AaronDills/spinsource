<?php

namespace App\Support;

use App\Jobs\Incremental\DiscoverChangedArtists;
use App\Jobs\Incremental\DiscoverChangedGenres;
use App\Jobs\Incremental\DiscoverNewArtistIds;
use App\Jobs\Incremental\DiscoverNewGenres;
use App\Jobs\Incremental\RefreshAlbumsForChangedArtists;
use App\Jobs\MusicBrainzSeedTracklists;
use App\Jobs\WikidataSeedAlbums;
use App\Jobs\WikidataSeedArtistIds;
use App\Jobs\WikidataSeedGenres;
use App\Models\JobRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class AdminJobManager
{
    /**
     * Jobs that can be managed from the admin console.
     *
     * Each entry should point to a job class that can execute with its
     * default constructor arguments (no additional input required).
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'discover_new_genres' => [
                'key' => 'discover_new_genres',
                'label' => 'Discover New Genres',
                'description' => 'Incrementally finds new genres and dispatches Wikidata seeding jobs.',
                'job_class' => DiscoverNewGenres::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Incremental',
            ],
            'discover_changed_genres' => [
                'key' => 'discover_changed_genres',
                'label' => 'Discover Changed Genres',
                'description' => 'Detects genre updates since the last run and queues refreshes.',
                'job_class' => DiscoverChangedGenres::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Incremental',
            ],
            'discover_new_artist_ids' => [
                'key' => 'discover_new_artist_ids',
                'label' => 'Discover New Artists',
                'description' => 'Fetches newly created artists and dispatches enrichment batches.',
                'job_class' => DiscoverNewArtistIds::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Incremental',
            ],
            'discover_changed_artists' => [
                'key' => 'discover_changed_artists',
                'label' => 'Discover Changed Artists',
                'description' => 'Finds artists modified recently and updates their data.',
                'job_class' => DiscoverChangedArtists::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Incremental',
            ],
            'refresh_albums_for_changed_artists' => [
                'key' => 'refresh_albums_for_changed_artists',
                'label' => 'Refresh Albums for Changed Artists',
                'description' => 'Refreshes albums for artists detected in the change checkpoint.',
                'job_class' => RefreshAlbumsForChangedArtists::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Incremental',
            ],
            'seed_genres' => [
                'key' => 'seed_genres',
                'label' => 'Seed Genres (Backfill)',
                'description' => 'Runs the Wikidata genre backfill from the current checkpoint.',
                'job_class' => WikidataSeedGenres::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Backfill',
            ],
            'seed_artist_ids' => [
                'key' => 'seed_artist_ids',
                'label' => 'Seed Artist IDs (Backfill)',
                'description' => 'Runs the full artist ID backfill and enrichment chain.',
                'job_class' => WikidataSeedArtistIds::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Backfill',
            ],
            'seed_albums' => [
                'key' => 'seed_albums',
                'label' => 'Seed Albums (Backfill)',
                'description' => 'Fetches albums for known artists and backfills metadata.',
                'job_class' => WikidataSeedAlbums::class,
                'queue' => 'wikidata',
                'category' => 'Wikidata Backfill',
            ],
            'musicbrainz_seed_tracklists' => [
                'key' => 'musicbrainz_seed_tracklists',
                'label' => 'MusicBrainz Tracklist Sync',
                'description' => 'Seeds tracklist fetch jobs for albums missing track data.',
                'job_class' => MusicBrainzSeedTracklists::class,
                'queue' => 'musicbrainz',
                'category' => 'MusicBrainz',
            ],
        ];
    }

    /**
     * Get the queue connection name.
     */
    public function queueConnection(): string
    {
        return config('queue.default');
    }

    /**
     * Get the queue driver for the active connection.
     */
    public function queueDriver(): string
    {
        $connection = $this->queueConnection();

        return config("queue.connections.{$connection}.driver", $connection);
    }

    /**
     * Get job definitions with runtime status data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jobsWithStatus(): array
    {
        return collect($this->definitions())
            ->map(fn ($def) => $this->addStatus($def))
            ->sortBy(fn ($def) => "{$def['category']}|{$def['label']}")
            ->values()
            ->toArray();
    }

    /**
     * Dispatch a job by key.
     */
    public function dispatchJob(string $jobKey): array
    {
        $definition = $this->definitions()[$jobKey] ?? null;
        if (! $definition) {
            return [
                'dispatched' => false,
                'message' => 'Unknown job type',
            ];
        }

        $jobClass = $definition['job_class'];

        try {
            dispatch(new $jobClass);

            return [
                'dispatched' => true,
                'job' => $definition,
                'message' => "{$definition['label']} dispatched to {$definition['queue']} queue",
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch admin job', [
                'job' => $jobClass,
                'error' => $e->getMessage(),
            ]);

            return [
                'dispatched' => false,
                'message' => 'Dispatch failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Cancel queued/reserved jobs for a type and mark running JobRun rows as failed.
     */
    public function cancelJob(string $jobKey): array
    {
        $definition = $this->definitions()[$jobKey] ?? null;
        if (! $definition) {
            return [
                'ok' => false,
                'message' => 'Unknown job type',
            ];
        }

        $jobClass = $definition['job_class'];
        $jobName = class_basename($jobClass);
        $driver = $this->queueDriver();

        $removed = [
            'waiting' => 0,
            'reserved' => 0,
            'delayed' => 0,
        ];

        if ($driver === 'redis') {
            $removed = $this->purgeRedisQueue($definition['queue'], $jobClass);
        } elseif ($driver === 'database') {
            $removed = $this->purgeDatabaseQueue($definition['queue'], $jobClass);
        } else {
            return [
                'ok' => false,
                'message' => "Queue driver '{$driver}' not supported for cancellations",
            ];
        }

        $cancelledRuns = 0;
        if ($this->hasJobRunsTable()) {
            $cancelledRuns = JobRun::where('job_name', $jobName)
                ->where('status', JobRun::STATUS_RUNNING)
                ->update([
                    'status' => JobRun::STATUS_FAILED,
                    'finished_at' => now(),
                    'error_message' => 'Cancelled from admin console',
                ]);
        }

        return [
            'ok' => true,
            'message' => "Cancelled {$definition['label']} jobs",
            'removed' => $removed,
            'cancelled_runs' => $cancelledRuns,
        ];
    }

    /**
     * Add runtime status data to a job definition.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function addStatus(array $definition): array
    {
        $jobClass = $definition['job_class'];
        $jobName = class_basename($jobClass);

        $definition['job_name'] = $jobName;
        $definition['queue_counts'] = $this->queueCounts($definition['queue'], $jobClass);
        $definition['last_run'] = $this->formatRun($this->lastRun($jobName));
        $definition['last_success'] = $this->formatRun($this->lastSuccess($jobName));
        $definition['running'] = $this->runningRuns($jobName);

        return $definition;
    }

    public function failedJobsSummary(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [
                'exists' => false,
                'count' => 0,
                'groups' => [],
            ];
        }

        $totalCount = DB::table('failed_jobs')->count();

        if ($totalCount === 0) {
            return [
                'exists' => true,
                'count' => 0,
                'groups' => [],
            ];
        }

        // Process in chunks to avoid memory exhaustion with large datasets
        // Only fetch columns needed: id, queue, failed_at, and first line of exception
        // Note: chunk() requires orderBy, using id for consistent pagination
        $groups = [];

        DB::table('failed_jobs')
            ->select(['id', 'queue', 'failed_at', DB::raw('SUBSTRING(exception, 1, 500) as exception_snippet')])
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$groups) {
                foreach ($rows as $row) {
                    $normalized = $this->normalizeException($row->exception_snippet);
                    $signature = sha1($normalized);

                    if (! isset($groups[$signature])) {
                        $groups[$signature] = [
                            'signature' => $signature,
                            'message' => $normalized,
                            'count' => 0,
                            'queues' => [],
                            'example_id' => $row->id,
                            'latest_failed_at' => $row->failed_at,
                        ];
                    }

                    $groups[$signature]['count']++;
                    $groups[$signature]['queues'][$row->queue] = ($groups[$signature]['queues'][$row->queue] ?? 0) + 1;
                    $latest = Carbon::parse($groups[$signature]['latest_failed_at']);
                    $current = Carbon::parse($row->failed_at);
                    if ($current->gt($latest)) {
                        $groups[$signature]['latest_failed_at'] = $row->failed_at;
                        $groups[$signature]['example_id'] = $row->id;
                    }
                }
            });

        $groups = collect($groups)
            ->sortByDesc(fn ($g) => [$g['count'], $g['latest_failed_at']])
            ->map(function ($g) {
                $g['queues'] = collect($g['queues'])
                    ->map(fn ($count, $queue) => ['queue' => $queue, 'count' => $count])
                    ->values()
                    ->toArray();
                $g['latest_failed_at_human'] = Carbon::parse($g['latest_failed_at'])->diffForHumans();

                return $g;
            })
            ->values()
            ->toArray();

        return [
            'exists' => true,
            'count' => $totalCount,
            'groups' => $groups,
        ];
    }

    public function clearFailedJobs(?string $signature = null): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [
                'ok' => false,
                'message' => 'Failed jobs table not found',
            ];
        }

        if ($signature) {
            $uuids = $this->failedJobIdsForSignature($signature);
            if (empty($uuids)) {
                return [
                    'ok' => false,
                    'message' => 'No failed jobs found for this error',
                ];
            }
            $count = DB::table('failed_jobs')->whereIn('uuid', $uuids)->count();
            DB::table('failed_jobs')->whereIn('uuid', $uuids)->delete();
        } else {
            $count = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->delete();
        }

        return [
            'ok' => true,
            'cleared' => $count,
            'message' => $signature
                ? "{$count} failed jobs cleared for this error"
                : "{$count} failed jobs cleared",
        ];
    }

    public function retryFailedJobs(?string $signature = null): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [
                'ok' => false,
                'message' => 'Failed jobs table not found',
            ];
        }

        $ids = $signature ? $this->failedJobIdsForSignature($signature) : $this->allFailedJobIds();
        if (empty($ids)) {
            return [
                'ok' => false,
                'message' => 'No failed jobs to retry',
            ];
        }

        try {
            \Artisan::call('queue:retry', ['id' => implode(',', $ids)]);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Retry failed: '.$e->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'retried' => count($ids),
            'message' => count($ids).' failed jobs dispatched for retry',
        ];
    }

    /**
     * Get the latest run (any status) for a job name.
     */
    protected function lastRun(string $jobName): ?JobRun
    {
        if (! $this->hasJobRunsTable()) {
            return null;
        }

        return JobRun::lastRun($jobName);
    }

    /**
     * Get the latest successful run for a job name.
     */
    protected function lastSuccess(string $jobName): ?JobRun
    {
        if (! $this->hasJobRunsTable()) {
            return null;
        }

        return JobRun::lastSuccessful($jobName);
    }

    /**
     * Get running JobRun rows for a job name.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function runningRuns(string $jobName): array
    {
        if (! $this->hasJobRunsTable()) {
            return [];
        }

        return JobRun::where('job_name', $jobName)
            ->where('status', JobRun::STATUS_RUNNING)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get()
            ->map(fn ($run) => $this->formatRun($run))
            ->toArray();
    }

    /**
     * Check if the job_runs table exists.
     */
    protected function hasJobRunsTable(): bool
    {
        static $cached;

        if ($cached !== null) {
            return $cached;
        }

        $cached = Schema::hasTable((new JobRun)->getTable());

        return $cached;
    }

    /**
     * Format a JobRun model for API responses.
     */
    protected function formatRun(?JobRun $run): ?array
    {
        if (! $run) {
            return null;
        }

        $duration = null;
        if ($run->started_at && $run->finished_at) {
            $duration = $run->started_at->diffInSeconds($run->finished_at);
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'started_at_human' => $run->started_at?->diffForHumans(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'finished_at_human' => $run->finished_at?->diffForHumans(),
            'duration_seconds' => $duration,
            'duration_human' => $this->formatDuration($duration),
            'error_message' => $run->error_message,
            'totals' => $run->totals ?? [],
        ];
    }

    /**
     * Format seconds as a readable duration string.
     */
    protected function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0
                ? "{$minutes}m {$remainingSeconds}s"
                : "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? "{$hours}h {$remainingMinutes}m"
            : "{$hours}h";
    }

    /**
     * Get queue counts for a job class on a queue name.
     */
    protected function queueCounts(string $queue, string $jobClass): array
    {
        $driver = $this->queueDriver();

        if ($driver === 'redis') {
            return $this->redisQueueCounts($queue, $jobClass);
        }

        if ($driver === 'database') {
            return $this->databaseQueueCounts($queue, $jobClass);
        }

        return [
            'supported' => false,
            'driver' => $driver,
            'waiting' => 0,
            'reserved' => 0,
            'delayed' => 0,
            'message' => "Queue driver '{$driver}' not supported for inspection",
        ];
    }

    /**
     * Count queued/reserved/delayed jobs in Redis.
     */
    protected function redisQueueCounts(string $queue, string $jobClass): array
    {
        try {
            $redis = Redis::connection(config('queue.connections.redis.connection'));
            $redis->ping();
        } catch (\Throwable $e) {
            return [
                'supported' => false,
                'driver' => 'redis',
                'waiting' => 0,
                'reserved' => 0,
                'delayed' => 0,
                'message' => 'Redis unavailable: '.$e->getMessage(),
            ];
        }

        return [
            'supported' => true,
            'driver' => 'redis',
            'waiting' => $this->countRedisList($redis, "queues:{$queue}", $jobClass),
            'reserved' => $this->countRedisList($redis, "queues:{$queue}:reserved", $jobClass),
            'delayed' => $this->countRedisZset($redis, "queues:{$queue}:delayed", $jobClass),
            'message' => null,
        ];
    }

    /**
     * Count queued/reserved/delayed jobs in the database queue table.
     */
    protected function databaseQueueCounts(string $queue, string $jobClass): array
    {
        $table = config('queue.connections.database.table', 'jobs');
        if (! Schema::hasTable($table)) {
            return [
                'supported' => false,
                'driver' => 'database',
                'waiting' => 0,
                'reserved' => 0,
                'delayed' => 0,
                'message' => "Table '{$table}' not found",
            ];
        }

        $rows = DB::table($table)
            ->where('queue', $queue)
            ->get(['id', 'payload', 'reserved_at', 'available_at']);

        $now = now()->getTimestamp();

        $waiting = 0;
        $reserved = 0;
        $delayed = 0;

        foreach ($rows as $row) {
            if (! $this->payloadMatchesJob($row->payload, $jobClass)) {
                continue;
            }

            $isDelayed = $row->available_at > $now;
            $isReserved = $row->reserved_at !== null;

            if ($isReserved) {
                $reserved++;
            } elseif ($isDelayed) {
                $delayed++;
            } else {
                $waiting++;
            }
        }

        return [
            'supported' => true,
            'driver' => 'database',
            'waiting' => $waiting,
            'reserved' => $reserved,
            'delayed' => $delayed,
            'message' => null,
        ];
    }

    /**
     * Remove matching jobs from Redis queues.
     *
     * @return array<string, int>
     */
    protected function purgeRedisQueue(string $queue, string $jobClass): array
    {
        try {
            $redis = Redis::connection(config('queue.connections.redis.connection'));
            $redis->ping();
        } catch (\Throwable $e) {
            return [
                'waiting' => 0,
                'reserved' => 0,
                'delayed' => 0,
            ];
        }

        return [
            'waiting' => $this->purgeRedisList($redis, "queues:{$queue}", $jobClass),
            'reserved' => $this->purgeRedisList($redis, "queues:{$queue}:reserved", $jobClass),
            'delayed' => $this->purgeRedisZset($redis, "queues:{$queue}:delayed", $jobClass),
        ];
    }

    /**
     * Remove matching jobs from the database queue table.
     *
     * @return array<string, int>
     */
    protected function purgeDatabaseQueue(string $queue, string $jobClass): array
    {
        $table = config('queue.connections.database.table', 'jobs');
        if (! Schema::hasTable($table)) {
            return ['waiting' => 0, 'reserved' => 0, 'delayed' => 0];
        }

        $rows = DB::table($table)
            ->where('queue', $queue)
            ->get(['id', 'payload', 'reserved_at', 'available_at']);

        $now = now()->getTimestamp();

        $removed = [
            'waiting' => 0,
            'reserved' => 0,
            'delayed' => 0,
        ];

        foreach ($rows as $row) {
            if (! $this->payloadMatchesJob($row->payload, $jobClass)) {
                continue;
            }

            $isDelayed = $row->available_at > $now;
            $isReserved = $row->reserved_at !== null;

            DB::table($table)->where('id', $row->id)->delete();

            if ($isReserved) {
                $removed['reserved']++;
            } elseif ($isDelayed) {
                $removed['delayed']++;
            } else {
                $removed['waiting']++;
            }
        }

        return $removed;
    }

    /**
     * Count matching jobs in a Redis list.
     */
    protected function countRedisList($redis, string $key, string $jobClass): int
    {
        $items = $redis->lrange($key, 0, -1);
        if (! is_array($items)) {
            return 0;
        }

        $count = 0;
        foreach ($items as $payload) {
            if ($this->payloadMatchesJob($payload, $jobClass)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count matching jobs in a Redis sorted set (delayed queue).
     */
    protected function countRedisZset($redis, string $key, string $jobClass): int
    {
        $items = $redis->zrange($key, 0, -1);
        if (! is_array($items)) {
            return 0;
        }

        $count = 0;
        foreach ($items as $payload) {
            if ($this->payloadMatchesJob($payload, $jobClass)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove matching jobs from a Redis list and preserve order for remaining jobs.
     */
    protected function purgeRedisList($redis, string $key, string $jobClass): int
    {
        $items = $redis->lrange($key, 0, -1);
        if (! is_array($items) || count($items) === 0) {
            return 0;
        }

        $kept = [];
        $removed = 0;

        foreach ($items as $payload) {
            if ($this->payloadMatchesJob($payload, $jobClass)) {
                $removed++;
            } else {
                $kept[] = $payload;
            }
        }

        if ($removed > 0) {
            $redis->del($key);
            if (! empty($kept)) {
                $redis->rpush($key, ...$kept);
            }
        }

        return $removed;
    }

    /**
     * Remove matching jobs from a Redis sorted set (delayed queue).
     */
    protected function purgeRedisZset($redis, string $key, string $jobClass): int
    {
        $items = $redis->zrange($key, 0, -1);
        if (! is_array($items) || count($items) === 0) {
            return 0;
        }

        $removed = 0;
        foreach ($items as $payload) {
            if ($this->payloadMatchesJob($payload, $jobClass)) {
                $redis->zrem($key, $payload);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Check if a queue payload represents the given job class.
     */
    protected function payloadMatchesJob(string $payload, string $jobClass): bool
    {
        $decoded = json_decode($payload, true);

        if (is_array($decoded)) {
            $displayName = $decoded['displayName'] ?? null;
            $commandName = $decoded['data']['commandName'] ?? null;

            if ($displayName === $jobClass || $commandName === $jobClass) {
                return true;
            }
        }

        return str_contains($payload, $jobClass);
    }

    protected function normalizeException(?string $exception): string
    {
        if (! $exception) {
            return 'Unknown exception';
        }

        $lines = preg_split('/\\r?\\n/', trim($exception));
        $first = $lines[0] ?? 'Unknown exception';

        return mb_substr($first, 0, 400);
    }

    protected function failedJobIdsForSignature(string $signature): array
    {
        $ids = [];

        // Process in chunks to avoid memory exhaustion
        // orderBy is required for chunk() to work correctly
        DB::table('failed_jobs')
            ->select(['uuid', DB::raw('SUBSTRING(exception, 1, 500) as exception_snippet')])
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($signature, &$ids) {
                foreach ($rows as $row) {
                    $normalized = $this->normalizeException($row->exception_snippet);
                    if (sha1($normalized) === $signature) {
                        $ids[] = $row->uuid;
                    }
                }
            });

        return $ids;
    }

    protected function allFailedJobIds(): array
    {
        // Return UUIDs (not IDs) for queue:retry command
        // Limit to prevent overwhelming the queue with retries
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(1000)
            ->pluck('uuid')
            ->all();
    }
}
