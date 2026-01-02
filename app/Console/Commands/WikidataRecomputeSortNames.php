<?php

namespace App\Console\Commands;

use App\Jobs\WikidataRecomputeSortNames as WikidataRecomputeSortNamesJob;
use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataRecomputeSortNames extends Command
{
    protected $signature = 'wikidata:recompute-sort-names
        {--batch-size=50 : Number of artists to process per batch}';

    protected $description = 'Recompute sort_name for artists using Wikidata given/family names.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');

        $this->info('Fetching artists to recompute sort_name...');

        $totalArtists = Artist::whereNotNull('wikidata_qid')->count();

        if ($totalArtists === 0) {
            $this->info('No artists found with Wikidata QIDs.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalArtists} artists. Dispatching in batches of {$batchSize}...");

        Artist::whereNotNull('wikidata_qid')
            ->orderBy('id')
            ->select(['id', 'wikidata_qid'])
            ->chunkById($batchSize, function ($artists) {
                $qids = $artists->pluck('wikidata_qid')->filter()->unique()->values()->toArray();

                if (empty($qids)) {
                    return;
                }

                Log::info('Dispatching WikidataRecomputeSortNames batch', [
                    'count' => count($qids),
                ]);

                WikidataRecomputeSortNamesJob::dispatch($qids);
            });

        $this->info('Done dispatching recompute jobs.');

        return self::SUCCESS;
    }
}
