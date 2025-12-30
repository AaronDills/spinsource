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

    // Prevent yesterday’s START cursor uniqueness from blocking today’s run
    public int $uniqueFor = 60 * 60; // 1 hour

    public function __construct(
        public ?string $afterQid = null,
        public int $pageSize = 2000,
        public int $batchSize = 100, // how many QIDs per enrich job
    ) {}

    public function uniqueId(): string
    {
        $cursor = $this->afterQid ?? 'START';
        return "wikidata:artist_ids:after:{$cursor}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        Log::info('Wikidata artist ID page start', [
            'afterQid'  => $this->afterQid,
            'pageSize'  => $this->pageSize,
            'batchSize' => $this->batchSize,
        ]);

        $afterFilter = '';
        if ($this->afterQid && preg_match('/^Q\d+$/', $this->afterQid)) {
            $afterFilter = "FILTER(?artist > wd:{$this->afterQid})";
        }

        $sparql = Sparql::load('artist_ids_cursor', [
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
                'afterQid' => $this->afterQid,
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
                'afterQid' => $this->afterQid,
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

        // Cursor for next page = last item in this page
        $lastArtistUrl = data_get($bindings[$count - 1], 'artist.value');
        $nextAfterQid  = $this->qidFromEntityUrl($lastArtistUrl);

        Log::info('Wikidata artist ID page fetched', [
            'afterQid'    => $this->afterQid,
            'pageSize'    => $this->pageSize,
            'returned'    => $count,
            'uniqueQids'  => count($qids),
            'nextAfterQid'=> $nextAfterQid,
        ]);

        // Dispatch enrichment in smaller batches to keep per-job work bounded
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk)->onQueue($this->queue ?? 'default');
        }

        // If we got a full page and have a cursor, enqueue next page.
        if ($count === $this->pageSize && $nextAfterQid) {
            usleep(250_000);

            self::dispatch($nextAfterQid, $this->pageSize, $this->batchSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata artist ID page', [
                'nextAfterQid' => $nextAfterQid,
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
