<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class WikidataSeedGenres extends WikidataJob implements ShouldBeUnique
{
    /**
     * Cursor pagination using numeric O-ID:
     * - null = start from beginning
     * - integer = fetch items with O-ID > afterOid
     */
    public function __construct(
        public ?int $afterOid = null,
        public int $pageSize = 500,
        public bool $singlePage = false, // diagnostic mode: no continuation
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        // Prevent accidental duplicate page processing
        $cursor = $this->afterOid ?? 'START';

        return "wikidata:genres:after:{$cursor}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        Log::info('Wikidata genre seed page start', [
            'afterOid' => $this->afterOid,
            'pageSize' => $this->pageSize,
        ]);

        $afterFilter = '';
        if (is_int($this->afterOid) && $this->afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$this->afterOid})";
        }

        $sparql = DataSourceQuery::get('genres', 'wikidata', [
            'limit' => $this->pageSize,
            'after_filter' => $afterFilter,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        // If null, job was released due to 429 rate limit
        if ($response === null) {
            return;
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            Log::info('Wikidata genre seed completed (no more results)', [
                'afterOid' => $this->afterOid,
                'pageSize' => $this->pageSize,
            ]);

            return;
        }

        $pendingParents = [];

        foreach ($bindings as $row) {
            $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
            if (! $genreQid) {
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

            // Keep name nullable handling explicit (optional but clearer than array_filter magic)
            $payload = [
                'name' => $name ?: null,
                'description' => $description ?: null,
                'musicbrainz_id' => $mbid ?: null,
                'inception_year' => $inception,
                'country_id' => $countryId,
            ];

            Genre::updateOrCreate(['wikidata_id' => $genreQid], array_filter(
                $payload,
                static fn ($v) => $v !== null
            ));
        }

        if (! empty($pendingParents)) {
            $this->resolveParents($pendingParents);
        }

        // Compute next cursor from last binding's numeric O-ID
        $nextAfterOid = (int) data_get($bindings[$count - 1], 'oid.value');

        Log::info('Wikidata genre seed page done', [
            'afterOid' => $this->afterOid,
            'pageSize' => $this->pageSize,
            'count' => $count,
            'nextAfterOid' => $nextAfterOid,
        ]);

        // If we got a full page and have a valid cursor, enqueue next page (unless single-page mode)
        if ($count === $this->pageSize && $nextAfterOid > 0 && ! $this->singlePage) {
            usleep(250_000);

            self::dispatch($nextAfterOid, $this->pageSize, false);

            Log::info('Enqueued next Wikidata genre seed page', [
                'nextAfterOid' => $nextAfterOid,
                'pageSize' => $this->pageSize,
            ]);
        } elseif ($this->singlePage) {
            Log::info('Single-page mode: stopping after first page', [
                'afterOid' => $this->afterOid,
                'count' => $count,
            ]);
        }
    }

    private function resolveParents(array $pendingParents): void
    {
        $childQids = array_keys($pendingParents);
        $parentQids = array_values($pendingParents);

        $genres = Genre::query()
            ->whereIn('wikidata_id', array_merge($childQids, $parentQids))
            ->get()
            ->keyBy('wikidata_id');

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
