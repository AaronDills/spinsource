<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fetch and sync album tracklist from MusicBrainz.
 *
 * ## Idempotency Strategy
 *
 * This job is designed to be safely retried multiple times without side effects:
 *
 * 1. **Stable unique key**: Uses (album_id, musicbrainz_recording_id) as the unique
 *    constraint instead of position. Recording MBIDs are stable across releases.
 *
 * 2. **Upsert-based**: All track writes use upsert keyed on recording MBID,
 *    so retries update existing tracks rather than creating duplicates.
 *
 * 3. **Atomic updates**: Track sync happens in a transaction. Either all tracks
 *    are updated or none are (no partial states).
 *
 * 4. **Timestamp tracking**:
 *    - `tracklist_attempted_at`: Set at job start, tracks when we tried
 *    - `tracklist_fetched_at`: Set only on successful completion
 *    - `tracklist_fetch_attempts`: Incremented each attempt
 *
 * 5. **Graceful resume**: If partial data exists from a previous failed run,
 *    the upsert will update those records rather than failing.
 *
 * 6. **Safe track cleanup**: Only removes tracks not in the new set AFTER
 *    successful upsert, within the same transaction.
 *
 * @see \App\Models\Album - Has tracklist_* columns for tracking
 * @see \App\Models\Track - Uses musicbrainz_recording_id as stable key
 */
class MusicBrainzFetchTracklist extends MusicBrainzJob implements ShouldBeUnique
{
    public int $uniqueFor = 3600; // 1 hour

    public function __construct(
        public int $albumId,
        public bool $forceReselect = false,
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        return "musicbrainz:tracklist:album:{$this->albumId}";
    }

    public function handle(): void
    {
        $this->withHeartbeat(function () {
            $this->doHandle();
        }, ['album_id' => $this->albumId]);
    }

    protected function doHandle(): void
    {
        $album = Album::find($this->albumId);

        if (! $album) {
            Log::warning('MusicBrainz tracklist: Album not found', [
                'albumId' => $this->albumId,
            ]);
            return;
        }

        // Mark attempt timestamp and increment counter BEFORE any work
        // This ensures we track attempts even if the job fails
        $album->update([
            'tracklist_attempted_at' => now(),
            'tracklist_fetch_attempts' => ($album->tracklist_fetch_attempts ?? 0) + 1,
        ]);

        if (! $album->musicbrainz_release_group_mbid) {
            Log::warning('MusicBrainz tracklist: No release group MBID', [
                'albumId' => $this->albumId,
            ]);
            $this->recordHeartbeat('missing_release_group', ['album_id' => $this->albumId]);
            return;
        }

        // Determine which release to use
        $releaseId = $this->selectRelease($album);
        if (! $releaseId) {
            return; // Already logged in selectRelease
        }

        // Fetch tracklist from MusicBrainz
        $media = $this->fetchTracklist($releaseId);
        if ($media === null) {
            // Rate limited - job was released for retry
            $this->recordHeartbeat('rate_limited', ['album_id' => $this->albumId, 'release_id' => $releaseId]);
            return;
        }

        if (empty($media)) {
            Log::warning('MusicBrainz: No media/tracks found in release', [
                'albumId' => $this->albumId,
                'releaseId' => $releaseId,
            ]);
            $this->recordHeartbeat('no_media', ['album_id' => $this->albumId, 'release_id' => $releaseId]);
            return;
        }

        // Sync tracks to database (idempotent operation)
        $trackCount = $this->syncTracks($album, $releaseId, $media);

        // Mark successful completion
        $album->update([
            'tracklist_fetched_at' => now(),
            'selected_release_mbid' => $releaseId,
            'musicbrainz_release_mbid' => $album->musicbrainz_release_mbid ?? $releaseId,
        ]);

        Log::info('MusicBrainz: Tracklist synced successfully', [
            'albumId' => $album->id,
            'albumTitle' => $album->title,
            'releaseId' => $releaseId,
            'trackCount' => $trackCount,
            'attempts' => $album->tracklist_fetch_attempts,
        ]);
    }

    /**
     * Select which MusicBrainz release to use for tracklist.
     *
     * Uses existing selection if available (for stability), otherwise
     * picks the best release from the release group.
     *
     * @return string|null Release MBID or null if unavailable
     */
    protected function selectRelease(Album $album): ?string
    {
        // Use existing selection for stability (unless forced to reselect)
        if (! $this->forceReselect && $album->selected_release_mbid) {
            Log::debug('MusicBrainz: Using existing selected release', [
                'albumId' => $this->albumId,
                'releaseId' => $album->selected_release_mbid,
            ]);
            return $album->selected_release_mbid;
        }

        // Fetch releases for this release group
        $releases = $this->fetchReleases($album->musicbrainz_release_group_mbid);

        if ($releases === null) {
            // Rate limited - job will be retried
            $this->recordHeartbeat('rate_limited_releases', ['album_id' => $this->albumId]);
            return null;
        }

        if (empty($releases)) {
            Log::info('MusicBrainz: No releases found for release group', [
                'albumId' => $this->albumId,
                'releaseGroupId' => $album->musicbrainz_release_group_mbid,
            ]);
            $this->recordHeartbeat('no_releases', [
                'album_id' => $this->albumId,
                'release_group' => $album->musicbrainz_release_group_mbid,
            ]);
            return null;
        }

        // Pick the best release using scoring algorithm
        $bestRelease = $this->pickBestRelease($releases);
        $releaseId = $bestRelease['id'];

        $this->recordHeartbeat('selected_release', [
            'album_id' => $this->albumId,
            'release_id' => $releaseId,
        ]);

        return $releaseId;
    }

    /**
     * Sync tracks to database using idempotent upsert.
     *
     * Strategy:
     * 1. Parse all tracks from MusicBrainz response
     * 2. Upsert based on (album_id, musicbrainz_recording_id) - stable key
     * 3. Remove orphaned tracks (those not in the new set)
     * 4. All within a transaction for atomicity
     *
     * @return int Number of tracks synced
     */
    protected function syncTracks(Album $album, string $releaseId, array $media): int
    {
        $tracks = $this->parseTracksFromMedia($album->id, $releaseId, $media);

        if (empty($tracks)) {
            Log::warning('MusicBrainz: No tracks parsed from media', [
                'albumId' => $album->id,
                'releaseId' => $releaseId,
            ]);
            return 0;
        }

        // Separate tracks with and without recording IDs
        // Tracks WITH recording IDs use upsert (stable key)
        // Tracks WITHOUT recording IDs use position-based matching (fallback)
        $tracksWithRecordingId = array_filter($tracks, fn($t) => $t['musicbrainz_recording_id'] !== null);
        $tracksWithoutRecordingId = array_filter($tracks, fn($t) => $t['musicbrainz_recording_id'] === null);

        DB::transaction(function () use ($album, $tracksWithRecordingId, $tracksWithoutRecordingId, $tracks) {
            // Upsert tracks with recording IDs (stable, idempotent)
            if (! empty($tracksWithRecordingId)) {
                Track::upsert(
                    array_values($tracksWithRecordingId),
                    ['album_id', 'musicbrainz_recording_id'], // Unique key
                    ['musicbrainz_release_id', 'title', 'position', 'number', 'disc_number', 'length_ms', 'source', 'source_last_synced_at', 'updated_at']
                );
            }

            // Handle tracks without recording IDs (rare edge case)
            // These use position-based matching as fallback
            foreach ($tracksWithoutRecordingId as $trackData) {
                Track::updateOrCreate(
                    [
                        'album_id' => $trackData['album_id'],
                        'disc_number' => $trackData['disc_number'],
                        'position' => $trackData['position'],
                        'musicbrainz_recording_id' => null,
                    ],
                    $trackData
                );
            }

            // Remove orphaned tracks (not in the new set)
            // Only tracks with recording IDs that aren't in our new set
            $validRecordingIds = array_filter(
                array_column($tracks, 'musicbrainz_recording_id'),
                fn($id) => $id !== null
            );

            // Delete tracks with recording IDs not in the new set
            if (! empty($validRecordingIds)) {
                Track::where('album_id', $album->id)
                    ->whereNotNull('musicbrainz_recording_id')
                    ->whereNotIn('musicbrainz_recording_id', $validRecordingIds)
                    ->delete();
            }

            // For tracks without recording IDs, delete by position not in new set
            $validPositions = collect($tracksWithoutRecordingId)
                ->map(fn($t) => $t['disc_number'] . ':' . $t['position'])
                ->toArray();

            if (! empty($tracksWithoutRecordingId)) {
                Track::where('album_id', $album->id)
                    ->whereNull('musicbrainz_recording_id')
                    ->get(['id', 'disc_number', 'position'])
                    ->filter(fn($t) => ! in_array($t->disc_number . ':' . $t->position, $validPositions))
                    ->each(fn($t) => $t->delete());
            }
        });

        return count($tracks);
    }

    /**
     * Parse tracks from MusicBrainz media response.
     *
     * @return array Track data ready for upsert
     */
    protected function parseTracksFromMedia(int $albumId, string $releaseId, array $media): array
    {
        $tracks = [];
        $now = now();

        foreach ($media as $medium) {
            $discNumber = $medium['position'] ?? 1;
            $fallbackPosition = 0;

            foreach ($medium['tracks'] ?? [] as $track) {
                $fallbackPosition++;
                $recording = $track['recording'] ?? [];

                // Recording MBID is the stable identifier
                $recordingId = $recording['id'] ?? null;

                // Raw track number from MusicBrainz (may be "A1", "B2", etc.)
                $rawNumber = $track['number'] ?? null;

                // Compute numeric position
                $position = $this->parseTrackPosition($track, $fallbackPosition);

                $tracks[] = [
                    'album_id' => $albumId,
                    'musicbrainz_recording_id' => $recordingId,
                    'musicbrainz_release_id' => $releaseId,
                    'title' => $track['title'] ?? $recording['title'] ?? 'Unknown',
                    'position' => $position,
                    'number' => $rawNumber,
                    'disc_number' => $discNumber,
                    'length_ms' => $track['length'] ?? $recording['length'] ?? null,
                    'source' => 'musicbrainz',
                    'source_last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $tracks;
    }

    /**
     * Fetch all releases for a release group.
     *
     * @return array|null Null if rate-limited
     */
    private function fetchReleases(string $releaseGroupId): ?array
    {
        $response = $this->executeMusicBrainzRequest('release', [
            'release-group' => $releaseGroupId,
            'inc' => 'media',
            'limit' => 100,
        ]);

        if ($response === null) {
            return null; // Rate limited, job released
        }

        return $response->json('releases', []);
    }

    /**
     * Fetch full tracklist for a specific release.
     *
     * @return array|null Null if rate-limited
     */
    private function fetchTracklist(string $releaseId): ?array
    {
        $response = $this->executeMusicBrainzRequest("release/{$releaseId}", [
            'inc' => 'recordings',
        ]);

        if ($response === null) {
            return null;
        }

        return $response->json('media', []);
    }

    /**
     * Keywords indicating non-standard editions (case-insensitive).
     */
    private const EDITION_PENALTY_KEYWORDS = [
        'deluxe',
        'expanded',
        'remastered',
        'remaster',
        'anniversary',
        'bonus',
        'edition',
        'special',
        'collector',
        'limited',
        'super',
    ];

    /**
     * Pick the best release from a list using a scoring algorithm.
     *
     * Criteria (in order of importance):
     * - Official status (+100)
     * - Physical format like CD/Vinyl (+50)
     * - English-speaking country (+30)
     * - Track count (capped at +20)
     * - Release age - older is better (capped at +30)
     * - Has barcode (+10)
     * - Penalty for edition keywords (-25 per keyword)
     */
    private function pickBestRelease(array $releases): array
    {
        $scored = array_map(function ($release) {
            $score = 0;

            // Prefer "Official" status (not Promotion, Bootleg, etc.)
            $status = $release['status'] ?? '';
            if ($status === 'Official') {
                $score += 100;
            }

            // Prefer physical formats (CD, Vinyl) over digital
            $format = $release['media'][0]['format'] ?? '';
            $physicalFormats = ['CD', 'Vinyl', '12" Vinyl', '7" Vinyl', 'Cassette'];
            if (in_array($format, $physicalFormats)) {
                $score += 50;
            }

            // Prefer releases from English-speaking countries
            $country = $release['country'] ?? '';
            $preferredCountries = ['US', 'GB', 'AU', 'CA'];
            if (in_array($country, $preferredCountries)) {
                $score += 30;
            }

            // Prefer releases with more tracks (but cap to avoid deluxe editions)
            $trackCount = $release['media'][0]['track-count'] ?? 0;
            $score += min($trackCount, 20);

            // Prefer older releases (original releases tend to be more canonical)
            $date = $release['date'] ?? '';
            if (preg_match('/^(\d{4})/', $date, $matches)) {
                $year = (int) $matches[1];
                $age = max(0, min(30, date('Y') - $year));
                $score += $age;
            }

            // Prefer releases with barcode (indicates legitimate release)
            if (! empty($release['barcode'])) {
                $score += 10;
            }

            // Penalize special editions (deluxe, remastered, anniversary, etc.)
            $score -= $this->countEditionKeywords($release) * 25;

            return ['release' => $release, 'score' => $score];
        }, $releases);

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        Log::debug('MusicBrainz: Selected best release', [
            'releaseId' => $scored[0]['release']['id'],
            'title' => $scored[0]['release']['title'] ?? 'Unknown',
            'score' => $scored[0]['score'],
            'totalReleases' => count($releases),
        ]);

        return $scored[0]['release'];
    }

    /**
     * Parse track position to a stable numeric value.
     *
     * MusicBrainz provides:
     * - 'position': Usually a 1-indexed numeric position within the medium
     * - 'number': Display number, may be "A1", "B2" for vinyl, or numeric
     *
     * We prefer 'position' when valid, otherwise use the fallback counter.
     */
    private function parseTrackPosition(array $track, int $fallback): int
    {
        // Check for explicit numeric position from MusicBrainz
        if (isset($track['position'])) {
            $pos = (int) $track['position'];
            if ($pos > 0) {
                return $pos;
            }
        }

        // Fallback: use sequential counter per medium
        return $fallback;
    }

    /**
     * Count edition keywords in release title and disambiguation.
     */
    private function countEditionKeywords(array $release): int
    {
        $searchText = strtolower(
            ($release['title'] ?? '') . ' ' . ($release['disambiguation'] ?? '')
        );

        $count = 0;
        foreach (self::EDITION_PENALTY_KEYWORDS as $keyword) {
            if (str_contains($searchText, $keyword)) {
                $count++;
            }
        }

        return $count;
    }
}
