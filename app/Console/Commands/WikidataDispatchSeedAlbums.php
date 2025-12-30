<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedAlbums;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedAlbums extends Command
{
    protected $signature = 'wikidata:dispatch-seed-albums
        {--artist-batch-size=25 : Number of local artists to include per WDQS batch}
        {--after-artist-id= : Start after this local artists.id (cursor pagination)}';

    protected $description = 'Dispatch queued jobs to seed albums from Wikidata for existing artists';

    public function handle(): int
    {
        $artistBatchSize = max(5, min(100, (int) $this->option('artist-batch-size')));
        $afterArtistId = $this->option('after-artist-id');

        if ($afterArtistId !== null && (!ctype_digit((string) $afterArtistId) || (int) $afterArtistId < 0)) {
            $this->error('Invalid --after-artist-id. Must be a non-negative integer (local artists.id).');
            return self::FAILURE;
        }

        $afterArtistId = $afterArtistId !== null ? (int) $afterArtistId : null;

        WikidataSeedAlbums::dispatch($afterArtistId, $artistBatchSize);

        Log::info('Dispatched Wikidata album seeding', [
            'afterArtistId' => $afterArtistId,
            'artistBatchSize' => $artistBatchSize,
        ]);

        $start = $afterArtistId ? "after artists.id={$afterArtistId}" : 'from beginning';
        $this->info("Dispatched album seeding job ({$start}, artistBatchSize={$artistBatchSize}).");

        return self::SUCCESS;
    }
}
