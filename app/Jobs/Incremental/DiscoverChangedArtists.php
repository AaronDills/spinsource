<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataEnrichArtists;
use App\Jobs\WikidataJob;
use App\Models\DataSourceQuery;
use App\Models\IngestionCheckpoint;
use Carbon\Carbon;

/**
 * Incremental discovery of CHANGED artist entities since last run.
 * Uses schema:dateModified to find recently modified artists.
 *
 * ## True delta approach
 *
 * This job queries Wikidata's schema:dateModified property to find artists
 * modified since the last successful run. A 48-hour overlap buffer is applied
 * to handle edge cases and eventual consistency.
 *
 * ## Tuning
 *
 * - pageSize: Number of changed artists per page (default 500)
 * - batchSize: Size of enrichment job batches (default 10)
 * - Increase pageSize to process more per run, decrease if WDQS times out
 */
class DiscoverChangedArtists extends WikidataJob
{
    public function __construct(
        public int $pageSize = 500,
        public int $batchSize = 10, // Reduced to avoid WDQS timeouts
        public ?string $afterModified = null, // ISO8601 for paging within this run
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('artists');

        // Use checkpoint with 48-hour overlap buffer for safety
        $sinceTs = $checkpoint->getChangedAtWithBuffer(48);
        $since = $sinceTs ? $sinceTs->toIso8601String() : Carbon::now()->subWeek()->toIso8601String();

        $this->startJobRun($this->afterModified ?? $since);

        $afterModifiedFilter = '';
        if ($this->afterModified) {
            $afterModifiedFilter = "FILTER(?modified > \"{$this->afterModified}\"^^xsd:dateTime)";
        }

        $sparql = DataSourceQuery::get('incremental/changed_artists_since', 'wikidata', [
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
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
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

        // Dispatch enrichment jobs in batches
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk);
        }

        // Update checkpoint with high-water mark
        if ($maxModified) {
            $checkpoint->bumpChangedAt($maxModified);

            // Store changed artist QIDs in meta for album refresh
            $existingQids = $checkpoint->getMeta('changed_artist_qids', []);
            $checkpoint->setMeta('changed_artist_qids', array_unique(array_merge($existingQids, $qids)));
        }

        $this->finishJobRun($maxModified?->toIso8601String());

        // Continue paging if we got a full page
        if ($count === $this->pageSize && $maxModified) {
            self::dispatch($this->pageSize, $this->batchSize, $maxModified->toIso8601String());
        }
    }
}
