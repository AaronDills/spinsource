<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class WikidataSync extends Command
{
    protected $signature = 'wikidata:sync
        {--force : Run even if a lock exists}
        {--page-size=500 : Page size for Wikidata seed jobs}
        {--genres-after= : Start genres after this QID (e.g. Q12345)}
        {--artists-after= : Start artists after this QID (e.g. Q12345)}
        {--artist-batch-size=100 : Artist QIDs per enrich job}';

    protected $description = 'Sync reference + core music data from Wikidata';

    public function handle(): int
    {
        $lock = Cache::lock('wikidata:sync', 6 * 60 * 60); // 6 hour lock

        if (! $this->option('force') && ! $lock->get()) {
            $this->warn('Another wikidata:sync is already running. Exiting.');
            return self::SUCCESS;
        }

        try {
            $this->info('Starting Wikidata sync...');

            // Allow bigger pages for cheap ID queries (artists). Genres still fine at 500-1000.
            $pageSize = max(25, min(5000, (int) $this->option('page-size')));

            $artistBatchSize = max(10, min(500, (int) $this->option('artist-batch-size')));

            $steps = [
                [
                    'cmd' => 'wikidata:dispatch-seed-genres',
                    'args' => array_filter([
                        '--page-size' => $pageSize,
                        '--after-qid' => $this->option('genres-after') ?: null,
                    ], fn ($v) => $v !== null),
                ],
                [
                    'cmd' => 'wikidata:dispatch-seed-artists',
                    'args' => array_filter([
                        '--page-size'  => $pageSize,
                        '--batch-size' => $artistBatchSize,
                        '--after-qid'  => $this->option('artists-after') ?: null,
                    ], fn ($v) => $v !== null),
                ],
            ];

            foreach ($steps as $step) {
                $cmd = $step['cmd'];
                $args = $step['args'] ?? [];

                $this->line('→ Running: ' . $cmd . ($args ? ' ' . json_encode($args) : ''));
                $exit = Artisan::call($cmd, $args, $this->output);

                if ($exit !== self::SUCCESS) {
                    $this->error("Command failed: {$cmd}");
                    return $exit;
                }
            }

            $this->info('Wikidata sync dispatched successfully (jobs will continue on the queue).');
            return self::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }
}
