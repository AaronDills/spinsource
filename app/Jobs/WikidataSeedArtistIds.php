<?php

namespace App\Jobs;

use App\Models\DataSourceQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class WikidataSeedArtistIds extends WikidataJob implements ShouldBeUnique
{
    // Prevent yesterday's START cursor uniqueness from blocking today's run
    public int $uniqueFor = 60 * 60; // 1 hour

    /**
     * Cursor pagination using numeric O-ID:
     * - null = start from beginning
     * - integer = fetch items with O-ID > afterOid
     */
    public function __construct(
        public ?int $afterOid = null,
        public int $pageSize = 2000,
        public int $batchSize = 10, // how many QIDs per enrich job (reduced to avoid WDQS timeouts)
        public bool $singlePage = false, // diagnostic mode: no continuation
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        $cursor = $this->afterOid ?? 'START';

        return "wikidata:artist_ids:after:{$cursor}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        Log::info('Wikidata artist ID page start', [
            'afterOid' => $this->afterOid,
            'pageSize' => $this->pageSize,
            'batchSize' => $this->batchSize,
        ]);

        $afterFilter = '';
        if (is_int($this->afterOid) && $this->afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$this->afterOid})";
        }

        $sparql = DataSourceQuery::get('artist_ids', 'wikidata', [
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
            Log::info('Wikidata artist seeding completed (no more IDs)', [
                'afterOid' => $this->afterOid,
                'pageSize' => $this->pageSize,
            ]);

            return;
        }

        $qids = [];
        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if ($qid) {
                $qids[] = $qid;
            }
        }

        $qids = array_values(array_unique($qids));

        // Compute next cursor from last binding's numeric O-ID
        $nextAfterOid = (int) data_get($bindings[$count - 1], 'oid.value');

        Log::info('Wikidata artist ID page fetched', [
            'afterOid' => $this->afterOid,
            'pageSize' => $this->pageSize,
            'returned' => $count,
            'uniqueQids' => count($qids),
            'nextAfterOid' => $nextAfterOid,
        ]);

        // Dispatch enrichment in smaller batches to keep per-job work bounded
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk);
        }

        // If we got a full page and have a valid cursor, enqueue next page (unless single-page mode)
        if ($count === $this->pageSize && $nextAfterOid > 0 && ! $this->singlePage) {
            usleep(250_000);

            self::dispatch($nextAfterOid, $this->pageSize, $this->batchSize, false);

            Log::info('Enqueued next Wikidata artist ID page', [
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
}
