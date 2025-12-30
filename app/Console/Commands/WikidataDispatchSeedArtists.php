<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedArtistIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedArtists extends Command
{
    protected $signature = 'wikidata:dispatch-seed-artists
        {--page-size=200 : Artist ID page size}
        {--batch-size=50 : Artist QIDs per enrich query}
        {--offset=0 : Start offset for pagination}';

    protected $description = 'Dispatch queued jobs to seed artists from Wikidata';

    public function handle(): int
    {
        $pageSize = max(50, min(2000, (int) $this->option('page-size')));
        $batchSize = max(10, min(200, (int) $this->option('batch-size')));
        $offset = max(0, (int) $this->option('offset'));

        WikidataSeedArtistIds::dispatch($offset, $pageSize, $batchSize);

        Log::info('Dispatched Wikidata artist seeding', [
            'offset' => $offset,
            'pageSize' => $pageSize,
            'batchSize' => $batchSize,
        ]);

        $this->info("Dispatched first artist ID page job (offset={$offset}, pageSize={$pageSize}, batchSize={$batchSize}).");
        return self::SUCCESS;
    }
}
