<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataEnrichArtists;
use App\Jobs\WikidataJob;
use App\Models\IngestionCheckpoint;
use App\Support\Sparql;
use Illuminate\Support\Facades\Log;

/**
 * Incremental discovery of NEW artist entities by O-ID cursor.
 * Used by weekly sync to find artists added since last checkpoint.
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

        Log::info('Incremental: Discover new artist IDs start', [
            'afterOid'  => $afterOid,
            'pageSize'  => $this->pageSize,
            'batchSize' => $this->batchSize,
        ]);

        $afterFilter = '';
        if ($afterOid !== null && $afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$afterOid})";
        }

        $sparql = Sparql::load('incremental/new_artists', [
            'limit'        => $this->pageSize,
            'after_filter' => $afterFilter,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return; // Rate limited, job released
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            Log::info('Incremental: No new artists found', [
                'afterOid' => $afterOid,
            ]);
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

        Log::info('Incremental: New artist IDs discovered', [
            'afterOid'     => $afterOid,
            'count'        => $count,
            'uniqueQids'   => count($qids),
            'maxOid'       => $maxOid,
        ]);

        // Dispatch enrichment jobs in batches
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk);
        }

        // Update checkpoint
        $checkpoint->bumpSeenOid($maxOid);

        Log::info('Incremental: Artist checkpoint updated', [
            'newLastSeenOid' => $maxOid,
            'enrichJobsDispatched' => count($chunks),
        ]);

        // Continue paging if we got a full page
        if ($count === $this->pageSize) {
            self::dispatch($this->pageSize, $this->batchSize);

            Log::info('Incremental: Dispatched next new artist page');
        }
    }
}
