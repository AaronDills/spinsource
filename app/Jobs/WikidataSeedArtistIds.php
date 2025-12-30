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

    public function __construct(
        public int $offset,
        public int $pageSize = 200,
        public int $batchSize = 50, // how many QIDs per enrich job
    ) {}

    public function uniqueId(): string
    {
        return "wikidata:artist_ids:offset:{$this->offset}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        Log::info('Wikidata artist ID page start', [
            'offset' => $this->offset,
            'pageSize' => $this->pageSize,
            'batchSize' => $this->batchSize,
        ]);

        $sparql = Sparql::load('artist_ids', [
            'limit' => $this->pageSize,
            'offset' => $this->offset,
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
                'offset' => $this->offset,
                'pageSize' => $this->pageSize,
                'status' => optional($e->response)->status(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            Log::info('Wikidata artist seeding completed (no more IDs)', [
                'offset' => $this->offset,
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
        Log::info('Wikidata artist ID page fetched', [
            'offset' => $this->offset,
            'pageSize' => $this->pageSize,
            'returned' => $count,
            'uniqueQids' => count($qids),
        ]);

        // Dispatch enrichment in smaller batches to keep WDQS queries cheap.
        $chunks = array_chunk($qids, max(10, $this->batchSize));
        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk)->onQueue($this->queue ?? 'default');
        }

        // If we got a full page, enqueue next page.
        if ($count === $this->pageSize) {
            usleep(250_000);
            self::dispatch($this->offset + $this->pageSize, $this->pageSize, $this->batchSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata artist ID page', [
                'nextOffset' => $this->offset + $this->pageSize,
                'pageSize' => $this->pageSize,
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
