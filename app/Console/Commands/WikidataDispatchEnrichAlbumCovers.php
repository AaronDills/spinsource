<?php

namespace App\Console\Commands;

use App\Jobs\WikidataEnrichAlbumCovers;
use App\Models\Album;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchEnrichAlbumCovers extends Command
{
    protected $signature = 'wikidata:dispatch-enrich-album-covers
        {--batch-size=500 : Number of albums to process per batch}';

    protected $description = 'Dispatch jobs to enrich album covers from Wikidata for albums missing cover images.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');

        $this->info('Fetching albums to enrich with cover images...');

        $query = Album::query()
            ->whereNotNull('wikidata_qid')
            ->whereNull('cover_image_commons');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No albums found that need cover enrichment.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} albums. Dispatching in batches of {$batchSize}...");

        $query->orderBy('id')
            ->chunkById($batchSize, function ($albums) {
                $qids = $albums->pluck('wikidata_qid')->filter()->unique()->values()->toArray();

                if (empty($qids)) {
                    return;
                }

                Log::info('Dispatching WikidataEnrichAlbumCovers batch', [
                    'count' => count($qids),
                ]);

                WikidataEnrichAlbumCovers::dispatch($qids);
            });

        $this->info('Done dispatching enrichment jobs.');

        return self::SUCCESS;
    }
}
