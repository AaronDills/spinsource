<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataEnrichArtists;
use App\Jobs\WikidataJob;
use App\Models\DataSourceQuery;
use App\Models\IngestionCheckpoint;

/**
 * Incremental discovery of NEW artist entities by O-ID cursor.
 * Used by weekly sync to find artists added since last checkpoint.
 *
 * ## Tuning the page size (N)
 *
 * - pageSize controls how many artists are discovered per run
 * - Smaller values (100-200): Shorter jobs, gentler on WDQS, but more runs needed
 * - Larger values (500-1000): Fewer runs, but risk WDQS timeouts on complex queries
 *
 * ## How the cursor works
 *
 * This job uses numeric O-ID (Wikidata ordinal ID) as a cursor. Each run:
 * 1. Queries artists with O-ID greater than the last seen O-ID
 * 2. Processes the page and dispatches enrichment jobs
 * 3. Updates the checkpoint with the max O-ID seen
 * 4. Self-chains if a full page was returned
 */
class DiscoverNewArtistIds extends WikidataJob
{
    public function __construct(
        public int $pageSize = 500,
        public int $batchSize = 10, // Reduced to avoid WDQS timeouts
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('artists');
        $afterOid = $checkpoint->last_seen_oid;

        $this->startJobRun((string) $afterOid);

        $afterFilter = '';
        if ($afterOid !== null && $afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$afterOid})";
        }

        $sparql = DataSourceQuery::get('incremental/new_artists', 'wikidata', [
            'limit' => $this->pageSize,
            'after_filter' => $afterFilter,
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
            $this->finishJobRun((string) $afterOid);

            return;
        }

        $qids = [];
        $maxOid = $afterOid ?? 0;

        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            $oid = (int) data_get($row, 'oid.value', 0);

            if ($qid) {
                $qids[] = $qid;
            }
            if ($oid > $maxOid) {
                $maxOid = $oid;
            }
        }

        $qids = array_values(array_unique($qids));

        $this->incrementProcessed($count);
        $this->incrementCreated(count($qids));

        // Dispatch enrichment jobs in batches
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk);
        }

        // Update checkpoint
        $checkpoint->bumpSeenOid($maxOid);

        $this->finishJobRun((string) $maxOid);

        // Continue paging if we got a full page
        if ($count === $this->pageSize) {
            self::dispatch($this->pageSize, $this->batchSize);
        }
    }
}
