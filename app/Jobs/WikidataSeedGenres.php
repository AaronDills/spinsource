<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WikidataSeedGenres extends WikidataJob
{
    public function handle(): void
    {
        $this->logStart('Seed genres');

        $sparql = $this->sparqlLoader->load('genres/seed_genres');

        $response = $this->wikidata->querySparql($sparql);

        DataSourceQuery::create([
            'source' => 'wikidata',
            'query_type' => 'sparql',
            'query_name' => 'genres/seed_genres',
            'query' => $sparql,
        ]);

        $results = $response['results']['bindings'] ?? [];
        if (empty($results)) {
            $this->logEnd('Seed genres (no results)');

            return;
        }

        $countriesToUpsert = [];
        $genresToUpsert = [];

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

            $genresToUpsert[] = [
                'wikidata_qid' => $genreQid,
                'name' => $row['genreLabel']['value'] ?? $genreQid,
                'country_qid' => $countryQid,
            ];
        }

        DB::transaction(function () use ($countriesToUpsert, $genresToUpsert) {
            if (! empty($countriesToUpsert)) {
                Country::upsert(array_values($countriesToUpsert), ['wikidata_qid'], ['name']);
            }

            // Map country qid to id
            $countryIdByQid = collect();
            if (! empty($countriesToUpsert)) {
                $countryIdByQid = Country::whereIn('wikidata_qid', array_keys($countriesToUpsert))
                    ->get(['id', 'wikidata_qid'])
                    ->keyBy('wikidata_qid')
                    ->map(fn ($c) => $c->id);
            }

            foreach ($genresToUpsert as $g) {
                $countryId = $g['country_qid'] ? ($countryIdByQid[$g['country_qid']] ?? null) : null;

                Genre::updateOrCreate(
                    ['wikidata_qid' => $g['wikidata_qid']],
                    ['name' => $g['name'], 'country_id' => $countryId]
                );
            }
        });

        Log::info('Seeded genres', [
            'count' => count($genresToUpsert),
        ]);

        $this->logEnd('Seed genres', [
            'count' => count($genresToUpsert),
        ]);
    }
}
