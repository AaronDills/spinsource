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
        {--after-qid= : Start after this Wikidata QID (e.g. Q12345)}';

    protected $description = 'Dispatch queued jobs to seed artists from Wikidata';

    public function handle(): int
    {
        $pageSize  = max(50, min(5000, (int) $this->option('page-size')));
        $batchSize = max(10, min(500, (int) $this->option('batch-size')));
        $afterQid  = $this->option('after-qid');

        if ($afterQid !== null && ! preg_match('/^Q\d+$/', $afterQid)) {
            $this->error('Invalid --after-qid. Must be in the form Q12345.');
            return self::FAILURE;
        }

        WikidataSeedArtistIds::dispatch($afterQid, $pageSize, $batchSize);

        Log::info('Dispatched Wikidata artist seeding', [
            'afterQid'  => $afterQid,
            'pageSize'  => $pageSize,
            'batchSize' => $batchSize,
        ]);

        $start = $afterQid ? "after {$afterQid}" : 'from beginning';
        $this->info("Dispatched first artist ID page job ({$start}, pageSize={$pageSize}, batchSize={$batchSize}).");

        return self::SUCCESS;
    }
}
