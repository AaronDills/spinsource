<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Models\DataSourceQuery;
use App\Models\IngestionCheckpoint;
use Carbon\Carbon;

/**
 * Incremental discovery of CHANGED genre entities since last run.
 * Uses schema:dateModified to find recently modified genres.
 *
 * ## True delta approach
 *
 * Queries Wikidata's schema:dateModified property with a 48-hour overlap buffer.
 * Dispatches EnrichChangedGenres to update the found genres.
 *
 * ## Tuning
 *
 * - pageSize: Number of changed genres per page (default 200)
 * - Genres change rarely, so small page sizes are usually sufficient
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

        $this->startJobRun($this->afterModified ?? $since);

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
        $this->incrementApiCalls();

        if ($response === null) {
            $this->failJobRun('Rate limited - job released for retry');

            return;
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            $this->finishJobRun($this->afterModified ?? $since);

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

        $this->incrementProcessed($count);
        $this->incrementUpdated(count($qids));

        // Enrich changed genres by re-fetching from the main genres SPARQL
        if (! empty($qids)) {
            EnrichChangedGenres::dispatch($qids);
        }

        // Update checkpoint with high-water mark
        if ($maxModified) {
            $checkpoint->bumpChangedAt($maxModified);
        }

        $this->finishJobRun($maxModified?->toIso8601String());

        // Continue paging if we got a full page
        if ($count === $this->pageSize && $maxModified) {
            self::dispatch($this->pageSize, $maxModified->toIso8601String());
        }
    }
}
