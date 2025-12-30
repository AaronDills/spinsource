<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedAlbums;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedAlbums extends Command
{
    protected $signature = 'wikidata:dispatch-seed-albums
        {--batch-size=50 : Number of artists to process per batch}
        {--offset=0 : Start offset for artist pagination}';

    protected $description = 'Dispatch queued jobs to seed albums from Wikidata for existing artists';

    public function handle(): int
    {
        $batchSize = max(10, min(200, (int) $this->option('batch-size')));
        $offset = max(0, (int) $this->option('offset'));

        WikidataSeedAlbums::dispatch($offset, $batchSize);

        Log::info('Dispatched Wikidata album seeding', [
            'offset' => $offset,
            'batchSize' => $batchSize,
        ]);

        $this->info("Dispatched album seeding job (offset={$offset}, batchSize={$batchSize}).");
        return self::SUCCESS;
    }
}
