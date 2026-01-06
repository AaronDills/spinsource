<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WikidataSeedGenres extends WikidataJob
{
    public function __construct(
        public ?int $afterOid = 0,
        public int $limit = 10000
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->withHeartbeat(function () {
            $this->doHandle();
        });
    }

    protected function doHandle(): void
    {
        $this->logStart('Seed genres');

        $afterFilter = ($this->afterOid !== null && $this->afterOid > 0)
            ? "FILTER(?oid > {$this->afterOid})"
            : '';

        $sparql = DataSourceQuery::get('genres', 'wikidata', [
            'after_filter' => $afterFilter,
            'limit' => $this->limit,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            // Rate limited - job has been released
            return;
        }

        $results = $response->json('results.bindings', []);
        if (empty($results)) {
            $this->logEnd('Seed genres (no results)');

            return;
        }

        $countriesToUpsert = [];
        $genresToUpsert = [];

        foreach ($results as $row) {
            $genreQid = $this->qidFromEntityUrl($row['genre']['value'] ?? null);
            if (! $genreQid) {
                continue;
            }

            $countryQid = $this->qidFromEntityUrl($row['country']['value'] ?? null);

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

            $now = now();

            foreach ($genresToUpsert as $g) {
                $countryId = $g['country_qid'] ? ($countryIdByQid[$g['country_qid']] ?? null) : null;

                Genre::updateOrCreate(
                    ['wikidata_qid' => $g['wikidata_qid']],
                    [
                        'name' => $g['name'],
                        'country_id' => $countryId,
                        'source' => 'wikidata',
                        'source_last_synced_at' => $now,
                    ]
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
