<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Genre;
use App\Support\Sparql;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikidataSeedGenres implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    // Backoff schedule (seconds) for transient WDQS issues
    public array $backoff = [5, 15, 45, 120, 300];

    public function __construct(
        public int $offset,
        public int $pageSize = 100
    ) {}

    public function uniqueId(): string
    {
        // Prevent accidental duplicate page processing
        return "wikidata:genres:offset:{$this->offset}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        Log::info('Wikidata genre seed page start', [
            'offset' => $this->offset,
            'pageSize' => $this->pageSize,
        ]);

        $sparql = Sparql::load('genres', [
            'limit'  => $this->pageSize,
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
            $status = optional($e->response)->status();
            Log::warning('Wikidata genre seed request failed', [
                'offset' => $this->offset,
                'pageSize' => $this->pageSize,
                'status' => $status,
                'message' => $e->getMessage(),
            ]);

            // Re-throw so the job is marked failed and retried according to $tries/$backoff
            throw $e;
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            Log::info('Wikidata genre seed completed (no more results)', [
                'offset' => $this->offset,
                'pageSize' => $this->pageSize,
            ]);
            return;
        }

        $pendingParents = [];

        foreach ($bindings as $row) {
            $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
            if (! $genreQid) continue;

            $name        = data_get($row, 'genreLabel.value');
            $description = data_get($row, 'genreDescription.value');
            $mbid        = data_get($row, 'musicBrainzId.value');
            $inception   = $this->extractYear(data_get($row, 'inception.value'));

            $countryId   = null;
            $countryQid  = $this->qidFromEntityUrl(data_get($row, 'country.value'));
            $countryName = data_get($row, 'countryLabel.value');

            if ($countryQid && $countryName) {
                $country = Country::updateOrCreate(
                    ['wikidata_id' => $countryQid],
                    ['name' => $countryName]
                );
                $countryId = $country->id;
            }

            $parentQid = $this->qidFromEntityUrl(data_get($row, 'parentGenre.value'));
            if ($parentQid) {
                $pendingParents[$genreQid] = $parentQid;
            }

            $payload = array_filter([
                'name'           => $name,
                'description'    => $description,
                'musicbrainz_id' => $mbid,
                'inception_year' => $inception,
                'country_id'     => $countryId,
            ], static fn ($v) => $v !== null);

            Genre::updateOrCreate(['wikidata_id' => $genreQid], $payload);
        }

        if (count($pendingParents) > 0) {
            $this->resolveParents($pendingParents);
        }

        Log::info('Wikidata genre seed page done', [
            'offset' => $this->offset,
            'pageSize' => $this->pageSize,
            'count' => $count,
        ]);

        // If we got a full page, likely more results exist. Enqueue next page.
        if ($count === $this->pageSize) {
            // Gentle throttle between pages (queue-friendly; doesn’t block worker too long)
            usleep(250_000);

            self::dispatch($this->offset + $this->pageSize, $this->pageSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata genre seed page', [
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

    private function extractYear(?string $dateValue): ?int
    {
        if (! $dateValue) return null;
        $clean = ltrim($dateValue, '+');

        try {
            return Carbon::parse($clean)->year;
        } catch (\Throwable) {
            if (preg_match('/(\d{4})/', $clean, $m)) return (int) $m[1];
            return null;
        }
    }

    private function resolveParents(array $pendingParents): void
    {
        $childQids  = array_keys($pendingParents);
        $parentQids = array_values($pendingParents);

        $genres = Genre::query()
            ->whereIn('wikidata_id', array_merge($childQids, $parentQids))
            ->get()
            ->keyBy('wikidata_id');

        foreach ($pendingParents as $childQid => $parentQid) {
            $child  = $genres->get($childQid);
            $parent = $genres->get($parentQid);

            if (! $child || ! $parent) continue;

            if ($child->parent_genre_id !== $parent->id) {
                $child->parent_genre_id = $parent->id;
                $child->save();
            }
        }
    }
}
