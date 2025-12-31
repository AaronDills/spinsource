<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Models\Country;
use App\Models\Genre;
use App\Support\Sparql;
use Illuminate\Support\Facades\Log;

/**
 * Enrich a batch of changed genre QIDs.
 * Re-fetches full genre data from Wikidata and upserts.
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

        Log::info('Incremental: Enrich changed genres start', [
            'count' => count($this->genreQids),
        ]);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $this->genreQids));

        $sparql = Sparql::load('incremental/genre_enrich', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return; // Rate limited, job released
        }

        $bindings = $response->json('results.bindings', []);

        if (empty($bindings)) {
            Log::info('Incremental: No genre data returned');
            return;
        }

        $pendingParents = [];
        $upserted = 0;

        foreach ($bindings as $row) {
            $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
            if (! $genreQid) continue;

            $name        = data_get($row, 'genreLabel.value');
            $description = data_get($row, 'genreDescription.value');
            $mbid        = data_get($row, 'musicBrainzId.value');
            $inception   = $this->extractYear(data_get($row, 'inception.value'));

            $countryId   = null;
            $countryQid  = $this->qidFromEntityUrl(data_get($row, 'country.value'));
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
                'name'           => $name ?: null,
                'description'    => $description ?: null,
                'musicbrainz_id' => $mbid ?: null,
                'inception_year' => $inception,
                'country_id'     => $countryId,
            ];

            Genre::updateOrCreate(['wikidata_id' => $genreQid], array_filter(
                $payload,
                static fn ($v) => $v !== null
            ));

            $upserted++;
        }

        if (!empty($pendingParents)) {
            $this->resolveParents($pendingParents);
        }

        Log::info('Incremental: Changed genres enriched', [
            'requested' => count($this->genreQids),
            'upserted'  => $upserted,
        ]);
    }

    private function resolveParents(array $pendingParents): void
    {
        $childQids  = array_keys($pendingParents);
        $parentQids = array_values($pendingParents);

        $genres = Genre::query()
            ->whereIn('wikidata_id', array_merge($childQids, $parentQids))
            ->get()
            ->keyBy('wikidata_id');

        foreach ($pendingParents as $childQid => $parentQid) {
            $child  = $genres->get($childQid);
            $parent = $genres->get($parentQid);

            if (! $child || ! $parent) continue;

            if ($child->parent_genre_id !== $parent->id) {
                $child->parent_genre_id = $parent->id;
                $child->save();
            }
        }
    }
}
