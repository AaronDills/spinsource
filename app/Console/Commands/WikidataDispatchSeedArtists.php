<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedArtistIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedArtists extends Command
{
    protected $signature = 'wikidata:dispatch-seed-artists
        {--page-size=2000 : Artist ID page size}
        {--batch-size=100 : Artist QIDs per enrich job}
        {--after-oid= : Start after this Wikidata numeric O-ID (e.g. 12345 for Q12345)}';

    protected $description = 'Dispatch queued jobs to seed artists from Wikidata';

    public function handle(): int
    {
        $pageSize  = max(50, min(5000, (int) $this->option('page-size')));
        $batchSize = max(10, min(500, (int) $this->option('batch-size')));
        $afterOid  = $this->option('after-oid');

        if ($afterOid !== null) {
            if (! ctype_digit($afterOid) || (int) $afterOid <= 0) {
                $this->error('Invalid --after-oid. Must be a positive integer (e.g. 12345 for Q12345).');
                return self::FAILURE;
            }
            $afterOid = (int) $afterOid;
        }

        WikidataSeedArtistIds::dispatch($afterOid, $pageSize, $batchSize);

        Log::info('Dispatched Wikidata artist seeding', [
            'afterOid'  => $afterOid,
            'pageSize'  => $pageSize,
            'batchSize' => $batchSize,
        ]);

        $start = $afterOid ? "after O-ID {$afterOid}" : 'from beginning';
        $this->info("Dispatched first artist ID page job ({$start}, pageSize={$pageSize}, batchSize={$batchSize}).");

        return self::SUCCESS;
    }
}
