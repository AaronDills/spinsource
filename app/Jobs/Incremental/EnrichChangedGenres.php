<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnrichChangedGenres extends WikidataJob
{
    /** @param array<int,string> $genreQids */
    public function __construct(public array $genreQids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if (empty($this->genreQids)) {
            return;
        }

        $this->withHeartbeat(function () {
            $this->doHandle();
        }, ['qids' => count($this->genreQids)]);
    }

    protected function doHandle(): void
    {
        $this->logStart('Enrich changed genres', [
            'count' => count($this->genreQids),
        ]);

        $sparql = $this->sparqlLoader->load('genres/enrich_genres');

        $response = $this->wikidata->querySparql($sparql, [
            'genreQids' => $this->genreQids,
        ]);

        DataSourceQuery::create([
            'source' => 'wikidata',
            'query_type' => 'sparql',
            'query_name' => 'genres/enrich_genres',
            'query' => $sparql,
            'response_meta' => [
                'qids' => $this->genreQids,
            ],
        ]);

        $results = $response['results']['bindings'] ?? [];
        if (empty($results)) {
            $this->logEnd('Enrich changed genres (no results)', [
                'count' => count($this->genreQids),
            ]);

            return;
        }

        $countriesToUpsert = [];
        $genreUpdates = [];

        foreach ($results as $row) {
            $genreQid = $this->wikidata->extractQid($row['genre']['value'] ?? null);
            if (! $genreQid) {
                continue;
            }

            $countryQid = $this->wikidata->extractQid($row['country']['value'] ?? null);
            if ($countryQid) {
                $countriesToUpsert[$countryQid] = [
                    'wikidata_qid' => $countryQid,
                    'name' => $row['countryLabel']['value'] ?? $countryQid,
                ];
            }

            $genreUpdates[$genreQid] = [
                'name' => $row['genreLabel']['value'] ?? null,
                'country_qid' => $countryQid,
            ];
        }

        DB::transaction(function () use ($countriesToUpsert, $genreUpdates) {
            if (! empty($countriesToUpsert)) {
                Country::upsert(array_values($countriesToUpsert), ['wikidata_qid'], ['name']);
            }

            $countryIdByQid = collect();
            if (! empty($countriesToUpsert)) {
                $countryIdByQid = Country::whereIn('wikidata_qid', array_keys($countriesToUpsert))
                    ->get(['id', 'wikidata_qid'])
                    ->keyBy('wikidata_qid')
                    ->map(fn ($c) => $c->id);
            }

            $now = now();

            foreach ($genreUpdates as $qid => $data) {
                $countryId = $data['country_qid'] ? ($countryIdByQid[$data['country_qid']] ?? null) : null;

                Genre::where('wikidata_qid', $qid)->update([
                    'name' => $data['name'],
                    'country_id' => $countryId,
                    'source' => 'wikidata',
                    'source_last_synced_at' => $now,
                ]);
            }
        });

        Log::info('Enriched changed genres', [
            'count' => count($genreUpdates),
        ]);

        $this->logEnd('Enrich changed genres', [
            'count' => count($genreUpdates),
        ]);
    }
}
