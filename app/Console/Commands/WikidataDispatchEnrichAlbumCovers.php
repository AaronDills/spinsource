<?php

namespace App\Console\Commands;

use App\Jobs\WikidataEnrichAlbumCovers;
use App\Models\Album;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataDispatchEnrichAlbumCovers extends Command
{
    protected $signature = 'wikidata:dispatch-enrich-album-covers
        {--batch-size=500 : Number of albums per job}
        {--limit= : Maximum number of albums to process (default: all)}
        {--force : Also update albums that already have cover images}';

    protected $description = '[BACKFILL] Dispatch jobs to enrich albums with cover images from Wikidata. Fetches P18 (image) for albums missing cover_image_commons.';

    public function handle(): int
    {
        $batchSize = max(50, min(1000, (int) $this->option('batch-size')));
        $limit = $this->option('limit');
        $force = $this->option('force');

        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('Invalid --limit. Must be a positive integer.');

            return self::FAILURE;
        }

        $limit = $limit !== null ? (int) $limit : null;

        $this->warn('Fetching albums to enrich with cover images...');

        $query = Album::query()
            ->whereNotNull('wikidata_id');

        if (! $force) {
            $query->whereNull('cover_image_commons');
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No albums need cover image enrichment.');

            return self::SUCCESS;
        }

        $processCount = $limit ? min($limit, $totalCount) : $totalCount;
        $jobCount = (int) ceil($processCount / $batchSize);

        $this->info("Found {$totalCount} albums without cover images.");
        $this->info("Will process {$processCount} albums in {$jobCount} jobs (batch size: {$batchSize}).");

        $dispatched = 0;
        $albumsProcessed = 0;

        $query->orderBy('id')
            ->select(['id', 'wikidata_id'])
            ->chunk($batchSize, function ($albums) use (&$dispatched, &$albumsProcessed, $limit, $batchSize) {
                if ($limit !== null && $albumsProcessed >= $limit) {
                    return false; // Stop chunking
                }

                $qids = $albums->pluck('wikidata_id')->toArray();

                // Trim to limit if needed
                if ($limit !== null) {
                    $remaining = $limit - $albumsProcessed;
                    if (count($qids) > $remaining) {
                        $qids = array_slice($qids, 0, $remaining);
                    }
                }

                WikidataEnrichAlbumCovers::dispatch($qids);
                $dispatched++;
                $albumsProcessed += count($qids);

                $this->output->write('.');

                return $limit === null || $albumsProcessed < $limit;
            });

        $this->newLine();

        Log::info('Dispatched Wikidata album cover enrichment', [
            'totalAlbums' => $totalCount,
            'processedAlbums' => $albumsProcessed,
            'jobsDispatched' => $dispatched,
            'batchSize' => $batchSize,
            'force' => $force,
        ]);

        $this->info("Dispatched {$dispatched} jobs to enrich {$albumsProcessed} albums with cover images.");

        return self::SUCCESS;
    }
}
