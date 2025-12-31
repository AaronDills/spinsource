<?php

namespace App\Console\Commands;

use App\Jobs\WikidataSeedArtistIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchSeedArtists extends Command
{
    protected $signature = 'wikidata:dispatch-seed-artists
        {--page-size=2000 : Artist ID page size}
        {--batch-size=10 : Artist QIDs per enrich job (reduced to avoid WDQS timeouts)}
        {--after-oid= : Start after this Wikidata numeric O-ID (e.g. 12345 for Q12345)}
        {--single-page : Only process one page (diagnostic mode, no continuation)}';

    protected $description = '[BACKFILL] Dispatch queued jobs to seed ALL artists from Wikidata. Use for initial import or disaster recovery. For weekly sync, use wikidata:sync-weekly instead.';

    public function handle(): int
    {
        $pageSize = max(50, min(5000, (int) $this->option('page-size')));
        $batchSize = max(10, min(500, (int) $this->option('batch-size')));
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

        WikidataSeedArtistIds::dispatch($afterOid, $pageSize, $batchSize, $singlePage);

        Log::info('Dispatched Wikidata artist seeding (backfill)', [
            'afterOid' => $afterOid,
            'pageSize' => $pageSize,
            'batchSize' => $batchSize,
            'singlePage' => $singlePage,
        ]);

        $start = $afterOid ? "after O-ID {$afterOid}" : 'from beginning';
        $mode = $singlePage ? 'SINGLE PAGE' : 'FULL BACKFILL';
        $this->info("[{$mode}] Dispatched first artist ID page job ({$start}, pageSize={$pageSize}, batchSize={$batchSize}).");

        return self::SUCCESS;
    }
}
