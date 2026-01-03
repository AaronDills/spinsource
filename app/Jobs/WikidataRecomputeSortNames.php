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

        // Format QIDs as VALUES clause for SPARQL
        $values = implode(' ', array_map(fn ($qid) => "wd:{$qid}", $this->artistQids));

        $sparql = DataSourceQuery::get('artist_name_components', 'wikidata', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            // Rate limited - job has been released
            return;
        }

        $results = $response->json('results.bindings', []);

        $nameComponents = [];
        foreach ($results as $row) {
            $artist = $row['artist'] ?? null;
            if (! $artist) {
                continue;
            }

            $qid = $this->qidFromEntityUrl($artist['value'] ?? null);
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
