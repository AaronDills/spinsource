<?php

namespace App\Jobs\Incremental;

use App\Jobs\WikidataEnrichArtists;
use App\Jobs\WikidataJob;
use App\Models\IngestionCheckpoint;
use App\Support\Sparql;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Incremental discovery of CHANGED artist entities since last run.
 * Uses schema:dateModified to find recently modified artists.
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

        Log::info('Incremental: Discover changed artists start', [
            'since' => $since,
            'afterModified' => $this->afterModified,
            'pageSize' => $this->pageSize,
        ]);

        $afterModifiedFilter = '';
        if ($this->afterModified) {
            $afterModifiedFilter = "FILTER(?modified > \"{$this->afterModified}\"^^xsd:dateTime)";
        }

        $sparql = Sparql::load('incremental/changed_artists_since', [
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
            Log::info('Incremental: No changed artists found', [
                'since' => $since,
            ]);

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

        Log::info('Incremental: Changed artists discovered', [
            'since' => $since,
            'count' => $count,
            'uniqueQids' => count($qids),
            'maxModified' => $maxModified?->toIso8601String(),
        ]);

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

        Log::info('Incremental: Changed artists processed', [
            'enrichJobsDispatched' => count($chunks),
            'newLastChangedAt' => $maxModified?->toIso8601String(),
        ]);

        // Continue paging if we got a full page
        if ($count === $this->pageSize && $maxModified) {
            self::dispatch($this->pageSize, $this->batchSize, $maxModified->toIso8601String());

            Log::info('Incremental: Dispatched next changed artist page');
        }
    }
}
