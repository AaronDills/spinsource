<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WikidataSync extends Command
{
    protected $signature = 'wikidata:sync
        {--force : Run even if a lock exists}
        {--only= : Run only a specific step (genres, artists, albums)}
        {--sequential : Wait for each step to complete before starting the next}

        {--page-size=2000 : Page size for ID-based seed jobs (genres/artists)}
        {--artist-batch-size=100 : QIDs per artist enrich job}

        {--genres-after-oid= : Start genres after this numeric O-ID (e.g. 12345 for Q12345)}
        {--artists-after-oid= : Start artists after this numeric O-ID (e.g. 12345 for Q12345)}

        {--albums-after-artist-id= : Start albums after this local artists.id (e.g. 12345)}
        {--album-artist-batch-size=25 : Artists per album WDQS batch}';

    protected $description = 'Sync reference + core music data from Wikidata

Job Dependencies (must run in order):
  1. Genres  - standalone reference data
  2. Artists - depends on genres for artist_genre pivot linking
  3. Albums  - depends on artists existing in local DB

Use --sequential to ensure proper ordering (waits for queue between steps).
Use --only=<step> to run a single step manually.';

    public function handle(): int
    {
        $lock = Cache::lock('wikidata:sync', 24 * 60 * 60); // 24 hours for sequential

        if (! $this->option('force') && ! $lock->get()) {
            $this->warn('Another wikidata:sync is already running. Exiting.');

            return self::SUCCESS;
        }

        try {
            $this->info('Starting Wikidata sync...');
            $this->newLine();

            $only = $this->option('only');
            $sequential = $this->option('sequential');

            if ($only && ! in_array($only, ['genres', 'artists', 'albums'])) {
                $this->error('Invalid --only value. Must be: genres, artists, or albums');

                return self::FAILURE;
            }

            if (! $only && ! $sequential) {
                $this->warn('⚠️  Running all steps in parallel. Jobs depend on each other:');
                $this->line('   Genres → Artists → Albums');
                $this->line('   Use --sequential to run in proper order.');
                $this->newLine();
            }

            if ($sequential) {
                $this->info('🔄 Sequential mode: will wait for queue between steps');
                $this->newLine();
            }

            $pageSize = max(25, min(5000, (int) $this->option('page-size')));
            $artistBatchSize = max(10, min(500, (int) $this->option('artist-batch-size')));
            $albumsAfterArtistId = $this->option('albums-after-artist-id');
            $albumsAfterArtistId = $albumsAfterArtistId !== null ? (int) $albumsAfterArtistId : null;
            $albumArtistBatchSize = max(5, min(100, (int) $this->option('album-artist-batch-size')));

            $steps = [
                'genres' => [
                    'cmd' => 'wikidata:dispatch-seed-genres',
                    'args' => array_filter([
                        '--page-size' => $pageSize,
                        '--after-oid' => $this->option('genres-after-oid') ?: null,
                    ], fn ($v) => $v !== null),
                ],
                'artists' => [
                    'cmd' => 'wikidata:dispatch-seed-artists',
                    'args' => array_filter([
                        '--page-size' => $pageSize,
                        '--batch-size' => $artistBatchSize,
                        '--after-oid' => $this->option('artists-after-oid') ?: null,
                    ], fn ($v) => $v !== null),
                ],
                'albums' => [
                    'cmd' => 'wikidata:dispatch-seed-albums',
                    'args' => array_filter([
                        '--after-artist-id' => $albumsAfterArtistId,
                        '--artist-batch-size' => $albumArtistBatchSize,
                    ], fn ($v) => $v !== null),
                ],
            ];

            // Filter to single step if --only is specified
            if ($only) {
                $steps = [$only => $steps[$only]];
            }

            $stepNames = array_keys($steps);
            $lastStep = end($stepNames);

            foreach ($steps as $name => $step) {
                $cmd = $step['cmd'];
                $args = $step['args'] ?? [];

                $this->line("→ [{$name}] Dispatching: {$cmd}".($args ? ' '.json_encode($args) : ''));
                $exit = Artisan::call($cmd, $args, $this->output);

                if ($exit !== self::SUCCESS) {
                    $this->error("Command failed: {$cmd}");

                    return $exit;
                }

                // Wait for queue to drain before next step (if sequential and not last step)
                if ($sequential && $name !== $lastStep) {
                    $this->newLine();
                    $this->info("⏳ Waiting for {$name} jobs to complete...");
                    $this->waitForQueueToDrain();
                    $this->info("✓ {$name} complete!");
                    $this->newLine();
                }
            }

            $this->newLine();
            $this->info('Wikidata sync dispatched successfully.');

            if ($sequential) {
                $this->info('⏳ Waiting for final jobs to complete...');
                $this->waitForQueueToDrain();
                $this->info('✓ All jobs complete!');
            } else {
                $this->line('Jobs will continue processing on the queue.');
            }

            return self::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Wait for the job queue to be empty.
     */
    private function waitForQueueToDrain(): void
    {
        $lastCount = -1;
        $stableChecks = 0;

        while (true) {
            // Check both database jobs table and Redis queue
            $dbJobs = DB::table('jobs')->count();
            $redisJobs = $this->getRedisQueueCount();
            $totalJobs = $dbJobs + $redisJobs;

            if ($totalJobs === 0) {
                // Queue appears empty, but wait a moment to ensure no new jobs are dispatched
                $stableChecks++;
                if ($stableChecks >= 3) {
                    break;
                }
                sleep(2);

                continue;
            }

            $stableChecks = 0;

            if ($totalJobs !== $lastCount) {
                $this->output->write("\r   Jobs remaining: {$totalJobs}   ");
                $lastCount = $totalJobs;
            }

            sleep(5);
        }

        $this->output->write("\r".str_repeat(' ', 40)."\r"); // Clear the line
    }

    /**
     * Get count of jobs in Redis queue.
     */
    private function getRedisQueueCount(): int
    {
        try {
            $connection = config('queue.connections.redis.connection', 'default');
            $queue = config('queue.connections.redis.queue', 'default');
            $prefix = config('database.redis.options.prefix', '');

            $redis = app('redis')->connection($connection);

            $waiting = $redis->llen("{$prefix}queues:{$queue}");
            $delayed = $redis->zcard("{$prefix}queues:{$queue}:delayed");
            $reserved = $redis->zcard("{$prefix}queues:{$queue}:reserved");

            return $waiting + $delayed + $reserved;
        } catch (\Throwable) {
            return 0;
        }
    }
}
