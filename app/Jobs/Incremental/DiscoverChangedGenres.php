<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Models\IngestionCheckpoint;
use App\Models\DataSourceQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Incremental discovery of CHANGED genre entities since last run.
 * Uses schema:dateModified to find recently modified genres.
 */
class DiscoverChangedGenres extends WikidataJob
{
    public function __construct(
        public int $pageSize = 200,
        public ?string $afterModified = null,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('genres');

        // Use checkpoint with 48-hour overlap buffer for safety
        $sinceTs = $checkpoint->getChangedAtWithBuffer(48);
        $since = $sinceTs ? $sinceTs->toIso8601String() : Carbon::now()->subWeek()->toIso8601String();

        Log::info('Incremental: Discover changed genres start', [
            'since' => $since,
            'afterModified' => $this->afterModified,
            'pageSize' => $this->pageSize,
        ]);

        $afterModifiedFilter = '';
        if ($this->afterModified) {
            $afterModifiedFilter = "FILTER(?modified > \"{$this->afterModified}\"^^xsd:dateTime)";
        }

        $sparql = DataSourceQuery::get('incremental/changed_genres_since', 'wikidata', [
            'since' => $since,
            'after_modified_filter' => $afterModifiedFilter,
            'limit' => $this->pageSize,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return; // Rate limited, job released
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            Log::info('Incremental: No changed genres found', [
                'since' => $since,
            ]);

            return;
        }

        $qids = [];
        $maxModified = null;

        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
            $modified = data_get($row, 'modified.value');

            if ($qid) {
                $qids[] = $qid;
            }
            if ($modified) {
                $ts = Carbon::parse($modified);
                if ($maxModified === null || $ts->greaterThan($maxModified)) {
                    $maxModified = $ts;
                }
            }
        }

        $qids = array_values(array_unique($qids));

        Log::info('Incremental: Changed genres discovered', [
            'since' => $since,
            'count' => $count,
            'uniqueQids' => count($qids),
            'maxModified' => $maxModified?->toIso8601String(),
        ]);

        // Enrich changed genres by re-fetching from the main genres SPARQL
        // Note: genres are small enough that we can process inline
        if (! empty($qids)) {
            EnrichChangedGenres::dispatch($qids);
        }

        // Update checkpoint with high-water mark
        if ($maxModified) {
            $checkpoint->bumpChangedAt($maxModified);
        }

        Log::info('Incremental: Changed genres processed', [
            'qidsToEnrich' => count($qids),
            'newLastChangedAt' => $maxModified?->toIso8601String(),
        ]);

        // Continue paging if we got a full page
        if ($count === $this->pageSize && $maxModified) {
            self::dispatch($this->pageSize, $maxModified->toIso8601String());

            Log::info('Incremental: Dispatched next changed genre page');
        }
    }
}
