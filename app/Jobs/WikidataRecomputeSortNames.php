<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Models\DataSourceQuery;
use Illuminate\Support\Facades\Log;

/**
 * Recompute sort_name for existing artists using Wikidata name components.
 * Lightweight job that only fetches given/family names and updates sort_name.
 */
class WikidataRecomputeSortNames extends WikidataJob
{
    /** @param array<int,string> $artistQids */
    public function __construct(public array $artistQids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if (empty($this->artistQids)) {
            return;
        }

        $this->withHeartbeat(function () {
            $this->doHandle();
        }, ['qids' => count($this->artistQids)]);
    }

    protected function doHandle(): void
    {
        $this->logStart('Recompute artist sort names', [
            'count' => count($this->artistQids),
        ]);

        $sparql = $this->sparqlLoader->load('artists/name_components_for_sort');

        $response = $this->wikidata->querySparql($sparql, [
            'artistQids' => $this->artistQids,
        ]);

        DataSourceQuery::create([
            'source' => 'wikidata',
            'query_type' => 'sparql',
            'query_name' => 'artists/name_components_for_sort',
            'query' => $sparql,
            'response_meta' => [
                'qids' => $this->artistQids,
            ],
        ]);

        $results = $response['results']['bindings'] ?? [];

        $nameComponents = [];
        foreach ($results as $row) {
            $artist = $row['artist'] ?? null;
            if (! $artist) {
                continue;
            }

            $qid = $this->wikidata->extractQid($artist['value'] ?? null);
            if (! $qid) {
                continue;
            }

            $given = $row['givenNameLabel']['value'] ?? null;
            $family = $row['familyNameLabel']['value'] ?? null;

            $nameComponents[$qid] = [
                'given' => $given,
                'family' => $family,
            ];
        }

        $artists = Artist::whereIn('wikidata_qid', $this->artistQids)
            ->get(['id', 'wikidata_qid', 'name', 'sort_name', 'source', 'source_last_synced_at']);

        $updated = 0;
        $now = now();

        foreach ($artists as $artist) {
            $components = $nameComponents[$artist->wikidata_qid] ?? [];
            $given = $components['given'] ?? null;
            $family = $components['family'] ?? null;

            $newSort = $artist->sort_name;

            if ($family) {
                $newSort = $given ? "{$family}, {$given}" : $family;
            }

            if ($newSort !== $artist->sort_name && $newSort !== null) {
                $artist->sort_name = $newSort;
                $artist->source = 'wikidata';
                $artist->source_last_synced_at = $now;
                $artist->save();
                $updated++;
            }
        }

        Log::info('Recomputed artist sort names', [
            'artists' => $artists->count(),
            'updated' => $updated,
        ]);

        $this->logEnd('Recompute artist sort names', [
            'artists' => $artists->count(),
            'updated' => $updated,
        ]);
    }
}
