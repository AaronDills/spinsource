<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedGenres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedGenres extends Command
{
    protected $signature = 'wikidata:dispatch-seed-genres
        {--page-size=500 : Number of records per page}
        {--after-oid= : Start after this Wikidata numeric O-ID (e.g. 12345 for Q12345)}
        {--single-page : Only process one page (diagnostic mode, no continuation)}';

    protected $description = '[BACKFILL] Dispatch queued jobs to seed ALL genres from Wikidata. Use for initial import or disaster recovery. For weekly sync, use wikidata:sync-weekly instead.';

    public function handle(): int
    {
        $pageSize = max(25, min(2000, (int) $this->option('page-size')));
        $afterOid = $this->option('after-oid');
        $singlePage = $this->option('single-page');

        if ($afterOid !== null) {
            if (! ctype_digit($afterOid) || (int) $afterOid <= 0) {
                $this->error('Invalid --after-oid. Must be a positive integer (e.g. 12345 for Q12345).');

                return self::FAILURE;
            }
            $afterOid = (int) $afterOid;
        }

        $this->warn('⚠️  This is a BACKFILL command for initial import or disaster recovery.');
        $this->warn('   For weekly incremental sync, use: php artisan wikidata:sync-weekly');
        $this->newLine();

        WikidataSeedGenres::dispatch($afterOid, $pageSize, $singlePage);

        Log::info('Dispatched Wikidata genre seeding (backfill)', [
            'afterOid' => $afterOid,
            'pageSize' => $pageSize,
            'singlePage' => $singlePage,
        ]);

        $start = $afterOid ? "after O-ID {$afterOid}" : 'from beginning';
        $mode = $singlePage ? 'SINGLE PAGE' : 'FULL BACKFILL';
        $this->info("[{$mode}] Dispatched first page job ({$start}, pageSize={$pageSize}).");

        return self::SUCCESS;
    }
}
