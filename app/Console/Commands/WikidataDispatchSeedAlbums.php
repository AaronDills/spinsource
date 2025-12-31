<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedAlbums;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedAlbums extends Command
{
    protected $signature = 'wikidata:dispatch-seed-albums
        {--artist-batch-size=25 : Number of local artists to include per WDQS batch}
        {--after-artist-id= : Start after this local artists.id (cursor pagination)}
        {--single-page : Only process one batch (diagnostic mode, no continuation)}';

    protected $description = '[BACKFILL] Dispatch queued jobs to seed ALL albums from Wikidata for existing artists. Use for initial import or disaster recovery. For weekly sync, use wikidata:sync-weekly instead.';

    public function handle(): int
    {
        $artistBatchSize = max(5, min(100, (int) $this->option('artist-batch-size')));
        $afterArtistId   = $this->option('after-artist-id');
        $singlePage      = $this->option('single-page');

        if ($afterArtistId !== null && (!ctype_digit((string) $afterArtistId) || (int) $afterArtistId < 0)) {
            $this->error('Invalid --after-artist-id. Must be a non-negative integer (local artists.id).');
            return self::FAILURE;
        }

        $afterArtistId = $afterArtistId !== null ? (int) $afterArtistId : null;

        $this->warn('⚠️  This is a BACKFILL command for initial import or disaster recovery.');
        $this->warn('   For weekly incremental sync, use: php artisan wikidata:sync-weekly');
        $this->newLine();

        WikidataSeedAlbums::dispatch($afterArtistId, $artistBatchSize, $singlePage);

        Log::info('Dispatched Wikidata album seeding (backfill)', [
            'afterArtistId'   => $afterArtistId,
            'artistBatchSize' => $artistBatchSize,
            'singlePage'      => $singlePage,
        ]);

        $start = $afterArtistId ? "after artists.id={$afterArtistId}" : 'from beginning';
        $mode = $singlePage ? 'SINGLE PAGE' : 'FULL BACKFILL';
        $this->info("[{$mode}] Dispatched album seeding job ({$start}, artistBatchSize={$artistBatchSize}).");

        return self::SUCCESS;
    }
}
