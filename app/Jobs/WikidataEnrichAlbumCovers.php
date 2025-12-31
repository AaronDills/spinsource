<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\DataSourceQuery;
use Illuminate\Support\Facades\Log;

/**
 * Enriches albums with cover images from Wikidata.
 *
 * Takes a batch of album Wikidata QIDs, queries P18 (image) for each,
 * and updates the cover_image_commons field.
 *
 * Used for backfilling existing albums that were imported before
 * cover image support was added.
 */
class WikidataEnrichAlbumCovers extends WikidataJob
{
    public function __construct(
        public array $qids = [],
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        if (empty($this->qids)) {
            Log::info('WikidataEnrichAlbumCovers: No QIDs provided');

            return;
        }

        $qids = array_unique($this->qids);

        Log::info('WikidataEnrichAlbumCovers: Starting batch', [
            'count' => count($qids),
        ]);

        // Build VALUES clause for SPARQL
        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $qids));

        $sparql = DataSourceQuery::get('album_covers', 'wikidata', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        // If null, job was released due to rate limiting
        if ($response === null) {
            return;
        }

        $bindings = $response->json('results.bindings', []);

        // Process results
        $updates = [];
        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'album.value'));
            $coverImage = $this->commonsFilename(data_get($row, 'coverImage.value'));

            if ($qid && $coverImage) {
                $updates[$qid] = $coverImage;
            }
        }

        if (empty($updates)) {
            Log::info('WikidataEnrichAlbumCovers: No cover images found', [
                'queriedCount' => count($qids),
            ]);

            return;
        }

        // Update albums in batches
        $updated = 0;
        foreach ($updates as $qid => $coverImage) {
            $affected = Album::where('wikidata_id', $qid)
                ->whereNull('cover_image_commons')
                ->update(['cover_image_commons' => $coverImage]);
            $updated += $affected;
        }

        Log::info('WikidataEnrichAlbumCovers: Batch complete', [
            'queriedCount' => count($qids),
            'foundCount' => count($updates),
            'updatedCount' => $updated,
        ]);
    }

    /**
     * Extract Commons filename from Wikidata image URL.
     */
    private function commonsFilename(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        if (str_contains($value, 'Special:FilePath/')) {
            $value = substr($value, strrpos($value, 'Special:FilePath/') + strlen('Special:FilePath/'));
        } else {
            $slash = strrpos($value, '/');
            if ($slash !== false) {
                $value = substr($value, $slash + 1);
            }
        }

        $value = urldecode($value);

        return $value !== '' ? $value : null;
    }
}
