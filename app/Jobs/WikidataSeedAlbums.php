<?php

namespace App\Jobs;

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use App\Support\Sparql;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikidataSeedAlbums implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [5, 15, 45, 120, 300];

    public function __construct(
        public int $offset = 0,
        public int $batchSize = 50,
    ) {}

    public function uniqueId(): string
    {
        return "wikidata:albums:offset:{$this->offset}:size:{$this->batchSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        // Fetch a batch of artists from our DB who have wikidata_id
        $artists = Artist::query()
            ->whereNotNull('wikidata_id')
            ->orderBy('id')
            ->offset($this->offset)
            ->limit($this->batchSize)
            ->get(['id', 'wikidata_id']);

        if ($artists->isEmpty()) {
            Log::info('Wikidata album seeding completed (no more artists)', [
                'offset' => $this->offset,
                'batchSize' => $this->batchSize,
            ]);
            return;
        }

        Log::info('Wikidata album seeding batch start', [
            'offset' => $this->offset,
            'batchSize' => $this->batchSize,
            'artistCount' => $artists->count(),
        ]);

        // Build artist QID to ID map for linking albums
        $artistQidToId = $artists->pluck('id', 'wikidata_id')->toArray();
        $artistQids = array_keys($artistQidToId);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        $sparql = Sparql::load('albums', [
            'values' => $values,
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
            Log::warning('Wikidata album seeding request failed', [
                'offset' => $this->offset,
                'batchSize' => $this->batchSize,
                'status' => optional($e->response)->status(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $bindings = $response->json('results.bindings', []);

        if (count($bindings) === 0) {
            Log::info('Wikidata album batch returned no albums', [
                'offset' => $this->offset,
                'artistCount' => count($artistQids),
            ]);
        } else {
            $this->processAlbums($bindings, $artistQidToId);
        }

        // Dispatch next batch
        if ($artists->count() === $this->batchSize) {
            usleep(250_000);
            self::dispatch($this->offset + $this->batchSize, $this->batchSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata album batch', [
                'nextOffset' => $this->offset + $this->batchSize,
                'batchSize' => $this->batchSize,
            ]);
        } else {
            Log::info('Wikidata album seeding completed', [
                'finalOffset' => $this->offset,
            ]);
        }
    }

    private function processAlbums(array $bindings, array $artistQidToId): void
    {
        // Group by album to handle multiple rows per album
        $byAlbum = [];
        foreach ($bindings as $row) {
            $albumQid = $this->qidFromEntityUrl(data_get($row, 'album.value'));
            if (! $albumQid) continue;

            $artistQid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $artistQid || ! isset($artistQidToId[$artistQid])) continue;

            $byAlbum[$albumQid] ??= [
                'qid' => $albumQid,
                'title' => null,
                'description' => null,
                'artistId' => $artistQidToId[$artistQid],
                'albumTypeQid' => null,
                'publicationDate' => null,
                'musicBrainzReleaseGroupId' => null,
                'wikipediaUrl' => null,
            ];

            $byAlbum[$albumQid]['title'] = $byAlbum[$albumQid]['title'] ?? data_get($row, 'albumLabel.value');
            $byAlbum[$albumQid]['description'] = $byAlbum[$albumQid]['description'] ?? data_get($row, 'albumDescription.value');
            $byAlbum[$albumQid]['albumTypeQid'] = $byAlbum[$albumQid]['albumTypeQid'] ?? $this->qidFromEntityUrl(data_get($row, 'albumType.value'));
            $byAlbum[$albumQid]['publicationDate'] = $byAlbum[$albumQid]['publicationDate'] ?? data_get($row, 'publicationDate.value');
            $byAlbum[$albumQid]['musicBrainzReleaseGroupId'] = $byAlbum[$albumQid]['musicBrainzReleaseGroupId'] ?? data_get($row, 'musicBrainzReleaseGroupId.value');
            $byAlbum[$albumQid]['wikipediaUrl'] = $byAlbum[$albumQid]['wikipediaUrl'] ?? data_get($row, 'wikipediaUrl.value');
        }

        $upserted = 0;

        foreach ($byAlbum as $data) {
            if (! $data['title']) continue;

            $releaseDate = $this->parseDate($data['publicationDate']);
            $releaseYear = $releaseDate ? $releaseDate->year : $this->extractYear($data['publicationDate']);

            Album::updateOrCreate(
                ['wikidata_id' => $data['qid']],
                [
                    'title' => $data['title'],
                    'artist_id' => $data['artistId'],
                    'album_type' => $this->mapAlbumType($data['albumTypeQid']),
                    'release_year' => $releaseYear,
                    'release_date' => $releaseDate,
                    'description' => $data['description'],
                    'wikipedia_url' => $data['wikipediaUrl'],
                    'musicbrainz_release_group_id' => $data['musicBrainzReleaseGroupId'],
                ]
            );
            $upserted++;
        }

        Log::info('Wikidata album batch processed', [
            'albumsUpserted' => $upserted,
            'rowsReturned' => count($bindings),
        ]);
    }

    private function mapAlbumType(?string $qid): string
    {
        return match ($qid) {
            'Q482994' => AlbumType::ALBUM->value,       // studio album
            'Q169930' => AlbumType::EP->value,          // EP
            'Q134556' => AlbumType::SINGLE->value,      // single
            'Q222910' => AlbumType::COMPILATION->value, // compilation album
            'Q209939' => AlbumType::LIVE->value,        // live album
            'Q59481898' => AlbumType::REMIX->value,     // remix album
            'Q24672043' => AlbumType::SOUNDTRACK->value, // soundtrack album
            default => AlbumType::OTHER->value,
        };
    }

    private function qidFromEntityUrl(?string $url): ?string
    {
        if (! $url) return null;
        $pos = strrpos($url, '/');
        if ($pos === false) return null;
        $qid = substr($url, $pos + 1);
        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
    }

    private function parseDate(?string $dateValue): ?Carbon
    {
        if (! $dateValue) return null;
        $clean = ltrim($dateValue, '+');

        try {
            return Carbon::parse($clean);
        } catch (\Throwable) {
            return null;
        }
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
}
