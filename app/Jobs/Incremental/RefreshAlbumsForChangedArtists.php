<?php

namespace App\Jobs\Incremental;

use App\Enums\AlbumType;
use App\Jobs\WikidataJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\IngestionCheckpoint;
use App\Support\Sparql;
use Illuminate\Support\Facades\Log;

/**
 * Refresh albums for artists that have changed since last run.
 * Uses the changed_artist_qids stored in the checkpoint meta.
 */
class RefreshAlbumsForChangedArtists extends WikidataJob
{
    public int $timeout = 180;

    public function __construct(
        public array $artistQids = [],
        public int $chunkSize = 25,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // If no specific QIDs provided, load from checkpoint meta
        $qids = $this->artistQids;

        if (empty($qids)) {
            $checkpoint = IngestionCheckpoint::forKey('artists');
            $qids = $checkpoint->getMeta('changed_artist_qids', []);

            // Clear the meta after reading
            if (!empty($qids)) {
                $checkpoint->setMeta('changed_artist_qids', []);
            }
        }

        if (empty($qids)) {
            Log::info('Incremental: No changed artists for album refresh');
            return;
        }

        Log::info('Incremental: Refresh albums for changed artists start', [
            'artistQids' => count($qids),
            'chunkSize'  => $this->chunkSize,
        ]);

        // Process in chunks to avoid huge SPARQL queries
        $chunks = array_chunk($qids, $this->chunkSize);

        foreach ($chunks as $index => $chunk) {
            if ($index === 0) {
                // Process first chunk inline
                $this->processArtistChunk($chunk);
            } else {
                // Dispatch remaining chunks as separate jobs
                self::dispatch($chunk, $this->chunkSize);
            }
        }

        Log::info('Incremental: Album refresh jobs dispatched', [
            'totalChunks' => count($chunks),
        ]);
    }

    private function processArtistChunk(array $artistQids): void
    {
        // Build artist QID -> local ID map
        $artists = Artist::query()
            ->whereIn('wikidata_id', $artistQids)
            ->get(['id', 'wikidata_id']);

        if ($artists->isEmpty()) {
            Log::info('Incremental: No local artists found for chunk');
            return;
        }

        $artistQidToId = $artists->pluck('id', 'wikidata_id')->toArray();
        $qidsInDb = array_keys($artistQidToId);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $qidsInDb));

        $sparql = Sparql::load('incremental/albums_for_artists', [
            'values' => $values,
        ]);

        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return; // Rate limited, job released
        }

        $bindings = $response->json('results.bindings', []);

        if (empty($bindings)) {
            Log::info('Incremental: No albums found for artist chunk', [
                'artistCount' => count($qidsInDb),
            ]);
            return;
        }

        $this->processAlbumsUpsert($bindings, $artistQidToId);
    }

    private function processAlbumsUpsert(array $bindings, array $artistQidToId): void
    {
        $byAlbum = [];

        foreach ($bindings as $row) {
            $albumQid = $this->qidFromEntityUrl(data_get($row, 'album.value'));
            if (! $albumQid) continue;

            $artistQid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $artistQid || ! isset($artistQidToId[$artistQid])) continue;

            $byAlbum[$albumQid] ??= [
                'wikidata_id' => $albumQid,
                'title' => null,
                'artist_id' => $artistQidToId[$artistQid],
                'album_type_qid' => null,
                'publication_date' => null,
                'musicbrainz_release_group_id' => null,
                'description' => null,
            ];

            $title = data_get($row, 'albumLabel.value');
            // Skip albums that still have Q-ID as title (no label in Wikidata)
            if ($title && !preg_match('/^Q\d+$/', $title)) {
                $byAlbum[$albumQid]['title'] = $byAlbum[$albumQid]['title'] ?? $title;
            }
            $byAlbum[$albumQid]['description'] = $byAlbum[$albumQid]['description'] ?? data_get($row, 'albumDescription.value');
            $byAlbum[$albumQid]['album_type_qid'] = $byAlbum[$albumQid]['album_type_qid'] ?? $this->qidFromEntityUrl(data_get($row, 'albumType.value'));
            $byAlbum[$albumQid]['publication_date'] = $byAlbum[$albumQid]['publication_date'] ?? data_get($row, 'publicationDate.value');
            $byAlbum[$albumQid]['musicbrainz_release_group_id'] = $byAlbum[$albumQid]['musicbrainz_release_group_id'] ?? data_get($row, 'musicBrainzReleaseGroupId.value');
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
                'musicbrainz_release_group_id' => $data['musicbrainz_release_group_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return;
        }

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
                'musicbrainz_release_group_id',
                'updated_at',
            ]
        );

        Log::info('Incremental: Albums upserted for changed artists', [
            'albumsUpserted' => count($rows),
            'skippedNoTitle' => $skippedNoTitle,
        ]);
    }

    private function mapAlbumType(?string $qid): string
    {
        return match ($qid) {
            'Q482994' => AlbumType::ALBUM->value,
            'Q169930' => AlbumType::EP->value,
            'Q134556' => AlbumType::SINGLE->value,
            'Q222910' => AlbumType::COMPILATION->value,
            'Q209939' => AlbumType::LIVE->value,
            'Q59481898' => AlbumType::REMIX->value,
            'Q24672043' => AlbumType::SOUNDTRACK->value,
            default => AlbumType::OTHER->value,
        };
    }
}
