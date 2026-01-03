<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\DataSourceQuery;
use Illuminate\Support\Facades\Log;

/**
 * Enrich albums with cover images from Wikidata.
 *
 * Takes a batch of album QIDs, queries Wikidata for cover images,
 * and updates all matching albums with cover_image_commons.
 */
class WikidataEnrichAlbumCovers extends WikidataJob
{
    /** @param array<int,string> $albumQids */
    public function __construct(public array $albumQids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if (empty($this->albumQids)) {
            return;
        }

        $this->withHeartbeat(function () {
            $this->doHandle();
        }, ['qids' => count($this->albumQids)]);
    }

    protected function doHandle(): void
    {
        $this->logStart('Enrich album covers', [
            'count' => count($this->albumQids),
        ]);

        // Format QIDs as VALUES clause for SPARQL
        $values = implode(' ', array_map(fn ($qid) => "wd:{$qid}", $this->albumQids));

        $sparql = DataSourceQuery::get('album_covers', 'wikidata', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            // Rate limited - job has been released
            return;
        }

        $results = $response->json('results.bindings', []);
        if (empty($results)) {
            $this->logEnd('Enrich album covers (no results)', [
                'count' => count($this->albumQids),
            ]);

            return;
        }

        $byQid = [];
        foreach ($results as $row) {
            $album = $row['album'] ?? null;
            $cover = $row['coverImage'] ?? null;

            if (! $album || ! $cover) {
                continue;
            }

            $qid = $this->qidFromEntityUrl($album['value'] ?? null);
            $coverValue = $cover['value'] ?? null;

            if (! $qid || ! $coverValue) {
                continue;
            }

            $byQid[$qid] = $coverValue;
        }

        $updated = 0;
        $now = now();

        foreach ($byQid as $qid => $coverCommons) {
            $affected = Album::where('wikidata_qid', $qid)
                ->whereNull('cover_image_commons')
                ->update([
                    'cover_image_commons' => $coverCommons,
                    'source' => 'wikidata',
                    'source_last_synced_at' => $now,
                ]);

            $updated += (int) $affected;
        }

        Log::info('Enriched album covers', [
            'unique_qids' => count($byQid),
            'rows_updated' => $updated,
        ]);

        $this->logEnd('Enrich album covers', [
            'unique_qids' => count($byQid),
            'rows_updated' => $updated,
        ]);
    }
}
