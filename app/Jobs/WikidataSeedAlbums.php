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

    // Prevent yesterday’s START cursor uniqueness from blocking today’s run
    public int $uniqueFor = 60 * 60; // 1 hour

    public function __construct(
        public ?int $afterArtistId = null, // cursor over local artists.id
        public int $artistBatchSize = 25,  // how many artists per WDQS request
    ) {}

    public function uniqueId(): string
    {
        $cursor = $this->afterArtistId ?? 'START';
        return "wikidata:albums:after_artist_id:{$cursor}:size:{$this->artistBatchSize}";
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        $artistBatchSize = max(5, min(100, (int) $this->artistBatchSize));

        // Cursor-based paging over local artists table (avoids DB OFFSET)
        $q = Artist::query()
            ->whereNotNull('wikidata_id')
            ->orderBy('id');

        if ($this->afterArtistId) {
            $q->where('id', '>', $this->afterArtistId);
        }

        $artists = $q->limit($artistBatchSize)->get(['id', 'wikidata_id']);

        if ($artists->isEmpty()) {
            Log::info('Wikidata album seeding completed (no more artists)', [
                'afterArtistId' => $this->afterArtistId,
                'artistBatchSize' => $artistBatchSize,
            ]);
            return;
        }

        $nextAfterArtistId = $artists->last()->id;

        Log::info('Wikidata album seeding batch start', [
            'afterArtistId' => $this->afterArtistId,
            'artistBatchSize' => $artistBatchSize,
            'artistCount' => $artists->count(),
            'nextAfterArtistId' => $nextAfterArtistId,
        ]);

        // Build artist QID -> local ID map for linking albums
        $artistQidToId = $artists->pluck('id', 'wikidata_id')->toArray();
        $artistQids = array_keys($artistQidToId);

        // VALUES list for WDQS
        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        // Use an aggregated SPARQL template if you adopt it:
        // resources/sparql/albums_agg.sparql
        // If you keep your existing template name "albums", this will still work.
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
                'afterArtistId' => $this->afterArtistId,
                'artistBatchSize' => $artistBatchSize,
                'status' => optional($e->response)->status(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $bindings = $response->json('results.bindings', []);

        if (count($bindings) === 0) {
            Log::info('Wikidata album batch returned no albums', [
                'afterArtistId' => $this->afterArtistId,
                'artistCount' => count($artistQids),
            ]);
        } else {
            $this->processAlbumsUpsert($bindings, $artistQidToId);
        }

        // Dispatch next batch
        if ($artists->count() === $artistBatchSize) {
            usleep(250_000);

            self::dispatch($nextAfterArtistId, $artistBatchSize)
                ->onQueue($this->queue ?? 'default');

            Log::info('Enqueued next Wikidata album batch', [
                'nextAfterArtistId' => $nextAfterArtistId,
                'artistBatchSize' => $artistBatchSize,
            ]);
        } else {
            Log::info('Wikidata album seeding completed', [
                'finalAfterArtistId' => $nextAfterArtistId,
            ]);
        }
    }

    /**
     * Expects one row per album (recommended).
     * If your current SPARQL returns multiple rows per album, this method still
     * defensively merges on album QID.
     */
    private function processAlbumsUpsert(array $bindings, array $artistQidToId): void
    {
        $byAlbum = [];

        foreach ($bindings as $row) {
            $albumQid = $this->qidFromEntityUrl(data_get($row, 'album.value'));
            if (! $albumQid) continue;

            // If using aggregated SPARQL, this will be a single artist value.
            // If using non-aggregated, this may vary row-to-row; first wins (MVP).
            $artistQid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $artistQid || ! isset($artistQidToId[$artistQid])) continue;

            $byAlbum[$albumQid] ??= [
                'wikidata_id' => $albumQid,
                'title' => null,
                'artist_id' => $artistQidToId[$artistQid],
                'album_type_qid' => null,
                'publication_date' => null,
                'musicbrainz_release_group_id' => null,
                'wikipedia_url' => null,
                'description' => null,
            ];

            $byAlbum[$albumQid]['title'] = $byAlbum[$albumQid]['title'] ?? data_get($row, 'albumLabel.value');
            $byAlbum[$albumQid]['description'] = $byAlbum[$albumQid]['description'] ?? data_get($row, 'albumDescription.value');
            $byAlbum[$albumQid]['album_type_qid'] = $byAlbum[$albumQid]['album_type_qid'] ?? $this->qidFromEntityUrl(data_get($row, 'albumType.value'));
            $byAlbum[$albumQid]['publication_date'] = $byAlbum[$albumQid]['publication_date'] ?? data_get($row, 'publicationDate.value');
            $byAlbum[$albumQid]['musicbrainz_release_group_id'] = $byAlbum[$albumQid]['musicbrainz_release_group_id'] ?? data_get($row, 'musicBrainzReleaseGroupId.value');
            $byAlbum[$albumQid]['wikipedia_url'] = $byAlbum[$albumQid]['wikipedia_url'] ?? data_get($row, 'wikipediaUrl.value');
        }

        $now = now();
        $rows = [];
        $skippedNoTitle = 0;

        foreach ($byAlbum as $data) {
            if (! $data['title']) {
                $skippedNoTitle++;
                continue;
            }

            $releaseDate = $this->parseDate($data['publication_date']);
            $releaseYear = $releaseDate ? (int) $releaseDate->year : $this->extractYear($data['publication_date']);

            $rows[] = [
                'wikidata_id' => $data['wikidata_id'],
                'title' => $data['title'],
                'artist_id' => $data['artist_id'],
                'album_type' => $this->mapAlbumType($data['album_type_qid']),
                'release_year' => $releaseYear,
                'release_date' => $releaseDate,
                'description' => $data['description'],
                'wikipedia_url' => $data['wikipedia_url'],
                'musicbrainz_release_group_id' => $data['musicbrainz_release_group_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            Log::info('Wikidata album batch processed (no upserts)', [
                'rowsReturned' => count($bindings),
                'albumsMerged' => count($byAlbum),
                'skippedNoTitle' => $skippedNoTitle,
            ]);
            return;
        }

        // Bulk upsert by wikidata_id (assumes wikidata_id is unique in albums table)
        Album::upsert(
            $rows,
            ['wikidata_id'],
            [
                'title',
                'artist_id',
                'album_type',
                'release_year',
                'release_date',
                'description',
                'wikipedia_url',
                'musicbrainz_release_group_id',
                'updated_at',
            ]
        );

        Log::info('Wikidata album batch processed', [
            'rowsReturned' => count($bindings),
            'albumsMerged' => count($byAlbum),
            'albumsUpserted' => count($rows),
            'skippedNoTitle' => $skippedNoTitle,
        ]);
    }

    private function mapAlbumType(?string $qid): string
    {
        return match ($qid) {
            'Q482994' => AlbumType::ALBUM->value,        // studio album
            'Q169930' => AlbumType::EP->value,           // EP
            'Q134556' => AlbumType::SINGLE->value,       // single
            'Q222910' => AlbumType::COMPILATION->value,  // compilation album
            'Q209939' => AlbumType::LIVE->value,         // live album
            'Q59481898' => AlbumType::REMIX->value,      // remix album
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
