<?php

namespace App\Jobs;

use App\Models\Album;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

/**
 * Batch job to dispatch tracklist fetching for albums.
 *
 * Uses cursor pagination over the albums table to process
 * albums that have a MusicBrainz release group ID but no tracks yet.
 */
class MusicBrainzSeedTracklists extends MusicBrainzJob implements ShouldBeUnique
{
    public int $uniqueFor = 3600; // 1 hour

    public function __construct(
        public ?int $afterAlbumId = null,
        public int $batchSize = 50,
        public bool $singlePage = false,
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        $cursor = $this->afterAlbumId ?? 'START';

        return "musicbrainz:seed_tracklists:after:{$cursor}:size:{$this->batchSize}";
    }

    public function handle(): void
    {
        $albums = Album::query()
            ->whereNotNull('musicbrainz_release_group_id')
            ->whereDoesntHave('tracks')
            ->orderBy('id')
            ->when($this->afterAlbumId, fn ($q) => $q->where('id', '>', $this->afterAlbumId))
            ->limit($this->batchSize)
            ->get(['id', 'title', 'musicbrainz_release_group_id']);

        if ($albums->isEmpty()) {
            Log::info('MusicBrainz: Tracklist seeding completed - no more albums to process');

            return;
        }

        $nextAfterAlbumId = $albums->last()->id;

        // Dispatch individual jobs for each album
        foreach ($albums as $album) {
            MusicBrainzFetchTracklist::dispatch($album->id);
        }

        Log::info('MusicBrainz: Tracklist batch dispatched', [
            'albumCount' => $albums->count(),
            'afterAlbumId' => $this->afterAlbumId,
            'nextAfterAlbumId' => $nextAfterAlbumId,
        ]);

        // Continue with next batch unless in single-page mode
        if ($albums->count() === $this->batchSize && ! $this->singlePage) {
            self::dispatch($nextAfterAlbumId, $this->batchSize, false);
        }
    }
}
