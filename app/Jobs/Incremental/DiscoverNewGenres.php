<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Jobs\WikidataSeedGenres;
use App\Models\DataSourceQuery;
use App\Models\IngestionCheckpoint;

/**
 * Incremental discovery of NEW genre entities by O-ID cursor.
 * Used by weekly sync to find genres added since last checkpoint.
 *
 * ## How it works
 *
 * Uses O-ID (Wikidata ordinal ID) to find genres created since the last run.
 * Dispatches WikidataSeedGenres to actually import the new genres.
 *
 * ## Tuning
 *
 * - pageSize: Controls discovery batch size (default 200)
 * - Genres are relatively rare compared to artists, so smaller page size is fine
 */
class DiscoverNewGenres extends WikidataJob
{
    public function __construct(
        public int $pageSize = 200,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('genres');
        $afterOid = $checkpoint->last_seen_oid;

        $this->startJobRun((string) $afterOid);

        $afterFilter = '';
        if ($afterOid !== null && $afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$afterOid})";
        }

        $sparql = DataSourceQuery::get('incremental/new_genres', 'wikidata', [
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

        $maxOid = $afterOid ?? 0;

        foreach ($bindings as $row) {
            $oid = (int) data_get($row, 'oid.value', 0);
            if ($oid > $maxOid) {
                $maxOid = $oid;
            }
        }

        $this->incrementProcessed($count);
        $this->incrementCreated($count);

        // Dispatch the existing genre seeder from the checkpoint
        // It will handle paging from the afterOid
        if ($afterOid !== null) {
            WikidataSeedGenres::dispatch($afterOid, $this->pageSize);
        } else {
            // First run - just update checkpoint, seeder handles full sync
            WikidataSeedGenres::dispatch(null, $this->pageSize);
        }

        // Update checkpoint
        $checkpoint->bumpSeenOid($maxOid);

        $this->finishJobRun((string) $maxOid);
    }
}
