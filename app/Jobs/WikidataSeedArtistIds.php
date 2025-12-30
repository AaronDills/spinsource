<?php

namespace App\Jobs;

use App\Support\Sparql;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikidataSeedArtistIds implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [5, 15, 45, 120, 300];

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
        public int $batchSize = 100, // how many QIDs per enrich job
    ) {}

    public function uniqueId(): string
    {
        $cursor = $this->afterOid ?? 'START';
        return "wikidata:artist_ids:after:{$cursor}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        Log::info('Wikidata artist ID page start', [
            'afterOid'  => $this->afterOid,
            'pageSize'  => $this->pageSize,
            'batchSize' => $this->batchSize,
        ]);

        $afterFilter = '';
        if (is_int($this->afterOid) && $this->afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$this->afterOid})";
        }

        $sparql = Sparql::load('artist_ids', [
            'limit'        => $this->pageSize,
            'after_filter' => $afterFilter,
        ]);

        try {
            $response = Http::withHeaders([
                    'Accept'          => 'application/sparql-results+json',
                    'User-Agent'      => $ua,
                    'Accept-Encoding' => 'gzip',
                    'Content-Type'    => 'application/x-www-form-urlencoded',
                ])
                ->connectTimeout(10)
                ->timeout(120)
                ->retry(4, 1500)
                ->asForm()
                ->post($endpoint, [
                    'format' => 'json',
                    'query'  => $sparql,
                ])
                ->throw();
        } catch (RequestException $e) {
            Log::warning('Wikidata artist ID page request failed', [
                'afterOid' => $this->afterOid,
                'pageSize' => $this->pageSize,
                'status'   => optional($e->response)->status(),
                'message'  => $e->getMessage(),
            ]);
            throw $e;
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
            if ($qid) $qids[] = $qid;
        }

        $qids = array_values(array_unique($qids));

        // Compute next cursor from last binding's numeric O-ID
        $nextAfterOid = (int) data_get($bindings[$count - 1], 'oid.value');

        Log::info('Wikidata artist ID page fetched', [
            'afterOid'     => $this->afterOid,
            'pageSize'     => $this->pageSize,
            'returned'     => $count,
            'uniqueQids'   => count($qids),
            'nextAfterOid' => $nextAfterOid,
        ]);

        // Dispatch enrichment in smaller batches to keep per-job work bounded
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk)->onQueue($this->queue ?? 'default');
        }

        // If we got a full page and have a valid cursor, enqueue next page.
        if ($count === $this->pageSize && $nextAfterOid > 0) {
            usleep(250_000);

            self::dispatch($nextAfterOid, $this->pageSize, $this->batchSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata artist ID page', [
                'nextAfterOid' => $nextAfterOid,
                'pageSize'     => $this->pageSize,
            ]);
        }
    }

    private function qidFromEntityUrl(?string $url): ?string
    {
        if (! $url) return null;
        $pos = strrpos($url, '/');
        if ($pos === false) return null;
        $qid = substr($url, $pos + 1);
        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
    }
}
