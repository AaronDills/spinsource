<?php

namespace App\Jobs;

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use App\Models\DataSourceQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class WikidataSeedAlbums extends WikidataJob implements ShouldBeUnique
{
    // Prevent yesterday's START cursor uniqueness from blocking today's run
    public int $uniqueFor = 60 * 60; // 1 hour

    public function __construct(
        public ?int $afterArtistId = null, // cursor over local artists.id
        public int $artistBatchSize = 25,  // how many artists per WDQS request
        public bool $singlePage = false, // diagnostic mode: no continuation
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        $cursor = $this->afterArtistId ?? 'START';

        return "wikidata:albums:after_artist_id:{$cursor}:size:{$this->artistBatchSize}";
    }

    public function handle(): void
    {
        $this->withHeartbeat(function () {
            $this->doHandle();
        }, ['afterArtistId' => $this->afterArtistId, 'batchSize' => $this->artistBatchSize]);
    }

    protected function doHandle(): void
    {
        $artistBatchSize = max(5, min(100, (int) $this->artistBatchSize));

        // Cursor-based paging over local artists table (avoids DB OFFSET)
        $q = Artist::query()
            ->whereNotNull('wikidata_qid')
            ->orderBy('id');

        if ($this->afterArtistId) {
            $q->where('id', '>', $this->afterArtistId);
        }

        $artists = $q->limit($artistBatchSize)->get(['id', 'wikidata_qid']);

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
        $artistQidToId = $artists->pluck('id', 'wikidata_qid')->toArray();
        $artistQids = array_keys($artistQidToId);

        // VALUES list for WDQS
        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        // Use an aggregated SPARQL template if you adopt it:
        // resources/sparql/albums_agg.sparql
        // If you keep your existing template name "albums", this will still work.
        $sparql = DataSourceQuery::get('albums', 'wikidata', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        // If null, job was released due to 429 rate limit
        if ($response === null) {
            return;
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

        // Dispatch next batch (unless single-page mode)
        if ($artists->count() === $artistBatchSize && ! $this->singlePage) {
            usleep(250_000);

            self::dispatch($nextAfterArtistId, $artistBatchSize, false);

            Log::info('Enqueued next Wikidata album batch', [
                'nextAfterArtistId' => $nextAfterArtistId,
                'artistBatchSize' => $artistBatchSize,
            ]);
        } elseif ($this->singlePage) {
            Log::info('Single-page mode: stopping after first batch', [
                'afterArtistId' => $this->afterArtistId,
                'artistCount' => $artists->count(),
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
            if (! $albumQid) {
                continue;
            }

            // If using aggregated SPARQL, this will be a single artist value.
            // If using non-aggregated, this may vary row-to-row; first wins (MVP).
            $artistQid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $artistQid || ! isset($artistQidToId[$artistQid])) {
                continue;
            }

            $byAlbum[$albumQid] ??= [
                'wikidata_qid' => $albumQid,
                'title' => null,
                'artist_id' => $artistQidToId[$artistQid],
                'album_type_qid' => null,
                'publication_date' => null,
                'musicbrainz_release_group_mbid' => null,
                'spotify_album_id' => null,
                'apple_music_album_id' => null,
                'description' => null,
                'cover_image' => null,
            ];

            $title = data_get($row, 'albumLabel.value');
            // Skip albums that still have Q-ID as title (no label in Wikidata)
            if ($title && ! preg_match('/^Q\d+$/', $title)) {
                $byAlbum[$albumQid]['title'] = $byAlbum[$albumQid]['title'] ?? $title;
            }
            $byAlbum[$albumQid]['description'] = $byAlbum[$albumQid]['description'] ?? data_get($row, 'albumDescription.value');
            $byAlbum[$albumQid]['album_type_qid'] = $byAlbum[$albumQid]['album_type_qid'] ?? $this->qidFromEntityUrl(data_get($row, 'albumType.value'));
            $byAlbum[$albumQid]['publication_date'] = $byAlbum[$albumQid]['publication_date'] ?? data_get($row, 'publicationDate.value');
            $byAlbum[$albumQid]['musicbrainz_release_group_mbid'] = $byAlbum[$albumQid]['musicbrainz_release_group_mbid'] ?? data_get($row, 'musicBrainzReleaseGroupId.value');
            $byAlbum[$albumQid]['spotify_album_id'] = $byAlbum[$albumQid]['spotify_album_id'] ?? data_get($row, 'spotifyAlbumId.value');
            $byAlbum[$albumQid]['apple_music_album_id'] = $byAlbum[$albumQid]['apple_music_album_id'] ?? data_get($row, 'appleMusicAlbumId.value');
            $byAlbum[$albumQid]['cover_image'] = $byAlbum[$albumQid]['cover_image'] ?? $this->commonsFilename(data_get($row, 'coverImage.value'));
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
                'wikidata_qid' => $data['wikidata_qid'],
                'title' => $data['title'],
                'artist_id' => $data['artist_id'],
                'album_type' => $this->mapAlbumType($data['album_type_qid']),
                'release_year' => $releaseYear,
                'release_date' => $releaseDate,
                'description' => $data['description'],
                'musicbrainz_release_group_mbid' => $data['musicbrainz_release_group_mbid'],
                'spotify_album_id' => $data['spotify_album_id'],
                'apple_music_album_id' => $data['apple_music_album_id'],
                'cover_image_commons' => $data['cover_image'],
                'source' => 'wikidata',
                'source_last_synced_at' => $now,
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

        // Bulk upsert by wikidata_qid (assumes wikidata_qid is unique in albums table)
        Album::upsert(
            $rows,
            ['wikidata_qid'],
            [
                'title',
                'artist_id',
                'album_type',
                'release_year',
                'release_date',
                'description',
                'musicbrainz_release_group_mbid',
                'spotify_album_id',
                'apple_music_album_id',
                'cover_image_commons',
                'source',
                'source_last_synced_at',
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

    /**
     * Extract Commons filename from Wikidata image URL.
     */
    private function commonsFilename(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        if (str_contains($value, 'Special:FilePath/')) {
            $value = substr($value, strrpos($value, 'Special:FilePath/') + strlen('Special:FilePath/'));
        } else {
            $slash = strrpos($value, '/');
            if ($slash !== false) {
                $value = substr($value, $slash + 1);
            }
        }

        $value = urldecode($value);

        return $value !== '' ? $value : null;
    }
}
