<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;

/**
 * Enrich a batch of changed genre QIDs.
 * Re-fetches full genre data from Wikidata and upserts.
 *
 * ## How it works
 *
 * Takes an array of genre Q-IDs, fetches their current data from Wikidata,
 * and updates the local database. Also resolves parent genre relationships.
 */
class EnrichChangedGenres extends WikidataJob
{
    public function __construct(
        public array $genreQids = [],
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        if (empty($this->genreQids)) {
            return;
        }

        $this->startJobRun();

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $this->genreQids));

        $sparql = DataSourceQuery::get('incremental/genre_enrich', 'wikidata', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);
        $this->incrementApiCalls();

        if ($response === null) {
            $this->failJobRun('Rate limited - job released for retry');

            return;
        }

        $bindings = $response->json('results.bindings', []);

        if (empty($bindings)) {
            $this->incrementSkipped(count($this->genreQids));
            $this->finishJobRun();

            return;
        }

        $pendingParents = [];
        $upserted = 0;

        foreach ($bindings as $row) {
            $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
            if (! $genreQid) {
                $this->incrementSkipped();

                continue;
            }

            $name = data_get($row, 'genreLabel.value');
            $description = data_get($row, 'genreDescription.value');
            $mbid = data_get($row, 'musicBrainzId.value');
            $inception = $this->extractYear(data_get($row, 'inception.value'));

            $countryId = null;
            $countryQid = $this->qidFromEntityUrl(data_get($row, 'country.value'));
            $countryName = data_get($row, 'countryLabel.value');

            if ($countryQid && $countryName) {
                $country = Country::updateOrCreate(
                    ['wikidata_id' => $countryQid],
                    ['name' => $countryName]
                );
                $countryId = $country->id;
            }

            $parentQid = $this->qidFromEntityUrl(data_get($row, 'parentGenre.value'));
            if ($parentQid) {
                $pendingParents[$genreQid] = $parentQid;
            }

            $payload = [
                'name' => $name ?: null,
                'description' => $description ?: null,
                'musicbrainz_id' => $mbid ?: null,
                'inception_year' => $inception,
                'country_id' => $countryId,
                'source' => 'wikidata',
                'source_last_synced_at' => now(),
            ];

            Genre::updateOrCreate(['wikidata_qid' => $genreQid], array_filter(
                $payload,
                static fn ($v) => $v !== null
            ));

            $upserted++;
        }

        $this->incrementProcessed(count($this->genreQids));
        $this->incrementUpdated($upserted);

        if (! empty($pendingParents)) {
            $this->resolveParents($pendingParents);
        }

        $this->finishJobRun();
    }

    private function resolveParents(array $pendingParents): void
    {
        $childQids = array_keys($pendingParents);
        $parentQids = array_values($pendingParents);

        $genres = Genre::query()
            ->whereIn('wikidata_qid', array_merge($childQids, $parentQids))
            ->get()
            ->keyBy('wikidata_qid');

        foreach ($pendingParents as $childQid => $parentQid) {
            $child = $genres->get($childQid);
            $parent = $genres->get($parentQid);

            if (! $child || ! $parent) {
                continue;
            }

            if ($child->parent_genre_id !== $parent->id) {
                $child->parent_genre_id = $parent->id;
                $child->save();
            }
        }
    }
}
