<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedGenres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedGenres extends Command
{
    protected $signature = 'wikidata:dispatch-seed-genres
        {--page-size=500 : Number of records per page}
        {--after-oid= : Start after this Wikidata numeric O-ID (e.g. 12345 for Q12345)}';

    protected $description = 'Dispatch queued jobs to seed genres from Wikidata';

    public function handle(): int
    {
        $pageSize = max(25, min(2000, (int) $this->option('page-size')));
        $afterOid = $this->option('after-oid');

        if ($afterOid !== null) {
            if (! ctype_digit($afterOid) || (int) $afterOid <= 0) {
                $this->error('Invalid --after-oid. Must be a positive integer (e.g. 12345 for Q12345).');
                return self::FAILURE;
            }
            $afterOid = (int) $afterOid;
        }

        WikidataSeedGenres::dispatch($afterOid, $pageSize);

        Log::info('Dispatched Wikidata genre seeding', [
            'afterOid' => $afterOid,
            'pageSize' => $pageSize,
        ]);

        $start = $afterOid ? "after O-ID {$afterOid}" : 'from beginning';
        $this->info("Dispatched first page job ({$start}, pageSize={$pageSize}).");

        return self::SUCCESS;
    }
}
