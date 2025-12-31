<?php

namespace App\Console\Commands;

use App\Jobs\WikidataRecomputeSortNames as RecomputeSortNamesJob;
use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataRecomputeSortNames extends Command
{
    protected $signature = 'wikidata:recompute-sort-names
        {--batch-size=50 : Artists per job batch}
        {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Recompute sort_name for all artists using Wikidata given/family name data';

    public function handle(): int
    {
        $batchSize = max(10, min(100, (int) $this->option('batch-size')));
        $sync = $this->option('sync');

        $this->info('Loading artists with Wikidata IDs...');

        $totalArtists = Artist::whereNotNull('wikidata_id')->count();

        if ($totalArtists === 0) {
            $this->warn('No artists with Wikidata IDs found.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalArtists} artists to process.");

        $processed = 0;
        $jobsDispatched = 0;

        $bar = $this->output->createProgressBar($totalArtists);
        $bar->start();

        Artist::whereNotNull('wikidata_id')
            ->select(['id', 'wikidata_id'])
            ->chunkById(1000, function ($artists) use ($batchSize, $sync, &$processed, &$jobsDispatched, $bar) {
                $qids = $artists->pluck('wikidata_id')->toArray();

                foreach (array_chunk($qids, $batchSize) as $batch) {
                    if ($sync) {
                        RecomputeSortNamesJob::dispatchSync($batch);
                    } else {
                        RecomputeSortNamesJob::dispatch($batch);
                    }
                    $jobsDispatched++;
                    $processed += count($batch);
                    $bar->setProgress($processed);
                }
            });

        $bar->finish();
        $this->newLine(2);

        $mode = $sync ? 'synchronously' : "to 'wikidata' queue";
        $this->info("Dispatched {$jobsDispatched} jobs {$mode} for {$totalArtists} artists.");

        Log::info('Dispatched Wikidata sort_name recompute', [
            'totalArtists' => $totalArtists,
            'jobsDispatched' => $jobsDispatched,
            'batchSize' => $batchSize,
            'sync' => $sync,
        ]);

        return self::SUCCESS;
    }
}
