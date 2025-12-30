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

    /**
     * Cursor pagination using numeric O-ID:
     * - null = start from beginning
     * - integer = fetch items with O-ID > afterOid
     */
    public function __construct(
        public ?int $afterOid = null,
        public int $pageSize = 500
    ) {}

    public function uniqueId(): string
    {
        // Prevent accidental duplicate page processing
        $cursor = $this->afterOid ?? 'START';
        return "wikidata:genres:after:{$cursor}:size:{$this->pageSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        Log::info('Wikidata genre seed page start', [
            'afterOid'  => $this->afterOid,
            'pageSize'  => $this->pageSize,
        ]);

        $afterFilter = '';
        if (is_int($this->afterOid) && $this->afterOid > 0) {
            $afterFilter = "FILTER(?oid > {$this->afterOid})";
        }

        $sparql = Sparql::load('genres', [
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
            $status = optional($e->response)->status();
            Log::warning('Wikidata genre seed request failed', [
                'afterOid' => $this->afterOid,
                'pageSize' => $this->pageSize,
                'status'   => $status,
                'message'  => $e->getMessage(),
            ]);

            throw $e;
        }

        $bindings = $response->json('results.bindings', []);
        $count = count($bindings);

        if ($count === 0) {
            Log::info('Wikidata genre seed completed (no more results)', [
                'afterOid' => $this->afterOid,
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

            // Keep name nullable handling explicit (optional but clearer than array_filter magic)
            $payload = [
                'name'           => $name ?: null,
                'description'    => $description ?: null,
                'musicbrainz_id' => $mbid ?: null,
                'inception_year' => $inception,
                'country_id'     => $countryId,
            ];

            Genre::updateOrCreate(['wikidata_id' => $genreQid], array_filter(
                $payload,
                static fn ($v) => $v !== null
            ));
        }

        if (!empty($pendingParents)) {
            $this->resolveParents($pendingParents);
        }

        // Compute next cursor from last binding's numeric O-ID
        $nextAfterOid = (int) data_get($bindings[$count - 1], 'oid.value');

        Log::info('Wikidata genre seed page done', [
            'afterOid'     => $this->afterOid,
            'pageSize'     => $this->pageSize,
            'count'        => $count,
            'nextAfterOid' => $nextAfterOid,
        ]);

        // If we got a full page and have a valid cursor, enqueue next page
        if ($count === $this->pageSize && $nextAfterOid > 0) {
            usleep(250_000);

            self::dispatch($nextAfterOid, $this->pageSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata genre seed page', [
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
