<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class WikidataSync extends Command
{
    protected $signature = 'wikidata:sync
        {--force : Run even if a lock exists}

        {--page-size=2000 : Page size for ID-based seed jobs (genres/artists)}
        {--artist-batch-size=100 : QIDs per artist enrich job}

        {--genres-after= : Start genres after this QID (e.g. Q12345)}
        {--artists-after= : Start artists after this QID (e.g. Q12345)}

        {--albums-after-artist-id= : Start albums after this local artists.id (e.g. 12345)}
        {--album-artist-batch-size=25 : Artists per album WDQS batch}';

    protected $description = 'Sync reference + core music data from Wikidata';

    public function handle(): int
    {
        // If your full sync can exceed this, increase it.
        $lock = Cache::lock('wikidata:sync', 6 * 60 * 60); // 6 hours

        if (! $this->option('force') && ! $lock->get()) {
            $this->warn('Another wikidata:sync is already running. Exiting.');
            return self::SUCCESS;
        }

        try {
            $this->info('Starting Wikidata sync...');

            $pageSize = max(25, min(5000, (int) $this->option('page-size')));
            $artistBatchSize = max(10, min(500, (int) $this->option('artist-batch-size')));

            $albumsAfterArtistId = $this->option('albums-after-artist-id');
            $albumsAfterArtistId = $albumsAfterArtistId !== null ? (int) $albumsAfterArtistId : null;

            $albumArtistBatchSize = max(5, min(100, (int) $this->option('album-artist-batch-size')));

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
                [
                    'cmd' => 'wikidata:dispatch-seed-albums',
                    'args' => array_filter([
                        '--after-artist-id'      => $albumsAfterArtistId,
                        '--artist-batch-size'    => $albumArtistBatchSize,
                    ], fn ($v) => $v !== null),
                ],
            ];

            foreach ($steps as $step) {
                $cmd  = $step['cmd'];
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
