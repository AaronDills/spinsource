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

        $sparql = $this->sparqlLoader->load('albums/enrich_album_covers');

        $response = $this->wikidata->querySparql($sparql, [
            'albumQids' => $this->albumQids,
        ]);

        DataSourceQuery::updateOrCreate(
            ['name' => 'albums/enrich_album_covers', 'data_source' => 'wikidata'],
            ['query_type' => 'sparql', 'query' => $sparql, 'response_meta' => ['qids' => $this->albumQids]]
        );

        $results = $response['results']['bindings'] ?? [];
        if (empty($results)) {
            $this->logEnd('Enrich album covers (no results)', [
                'count' => count($this->albumQids),
            ]);

            return;
        }

        $byQid = [];
        foreach ($results as $row) {
            $album = $row['album'] ?? null;
            $cover = $row['coverImageCommons'] ?? null;

            if (! $album || ! $cover) {
                continue;
            }

            $qid = $this->wikidata->extractQid($album['value'] ?? null);
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
