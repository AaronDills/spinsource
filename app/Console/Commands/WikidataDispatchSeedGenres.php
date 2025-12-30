<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedGenres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedGenres extends Command
{
    protected $signature = 'wikidata:dispatch-seed-genres
        {--page-size=500 : Number of records per page}
        {--after-qid= : Start after this Wikidata QID (e.g. Q12345)}';

    protected $description = 'Dispatch queued jobs to seed genres from Wikidata';

    public function handle(): int
    {
        $pageSize = max(25, min(2000, (int) $this->option('page-size')));
        $afterQid = $this->option('after-qid');

        if ($afterQid !== null && ! preg_match('/^Q\d+$/', $afterQid)) {
            $this->error('Invalid --after-qid. Must be in the form Q12345.');
            return self::FAILURE;
        }

        WikidataSeedGenres::dispatch($afterQid, $pageSize);

        Log::info('Dispatched Wikidata genre seeding', [
            'afterQid' => $afterQid,
            'pageSize' => $pageSize,
        ]);

        $start = $afterQid ? "after {$afterQid}" : 'from beginning';
        $this->info("Dispatched first page job ({$start}, pageSize={$pageSize}).");

        return self::SUCCESS;
    }
}
