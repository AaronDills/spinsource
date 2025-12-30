<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedGenres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedGenres extends Command
{
    protected $signature = 'wikidata:dispatch-seed-genres
        {--page-size=100}
        {--offset=0}';

    protected $description = 'Dispatch queued jobs to seed genres from Wikidata';

    public function handle(): int
    {
        $pageSize = max(25, min(2000, (int) $this->option('page-size')));
        $offset = max(0, (int) $this->option('offset'));

        WikidataSeedGenres::dispatch($offset, $pageSize);

        Log::info('Dispatched Wikidata genre seeding', [
            'offset' => $offset,
            'pageSize' => $pageSize,
        ]);

        $this->info("Dispatched first page job (offset={$offset}, pageSize={$pageSize}).");
        return self::SUCCESS;
    }
}
