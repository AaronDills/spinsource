<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataJob;
use App\Jobs\WikidataSeedGenres;
use App\Models\IngestionCheckpoint;
use App\Support\Sparql;
use Illuminate\Support\Facades\Log;

/**
 * Incremental discovery of NEW genre entities by O-ID cursor.
 * Used by weekly sync to find genres added since last checkpoint.
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

        Log::info('Incremental: Discover new genres start', [
            'afterOid'  => $afterOid,
            'pageSize'  => $this->pageSize,
        ]);

        $afterFilter = '';
        if ($afterOid !== null && $afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$afterOid})";
        }

        $sparql = Sparql::load('incremental/new_genres', [
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
            Log::info('Incremental: No new genres found', [
                'afterOid' => $afterOid,
            ]);
            return;
        }

        $maxOid = $afterOid ?? 0;

        foreach ($bindings as $row) {
            $oid = (int) data_get($row, 'oid.value', 0);
            if ($oid > $maxOid) {
                $maxOid = $oid;
            }
        }

        Log::info('Incremental: New genres discovered', [
            'afterOid' => $afterOid,
            'count'    => $count,
            'maxOid'   => $maxOid,
        ]);

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

        Log::info('Incremental: Genre checkpoint updated', [
            'newLastSeenOid' => $maxOid,
        ]);
    }
}
