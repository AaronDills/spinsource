<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $album = Album::find($this->albumId);

        if (! $album || ! $album->musicbrainz_release_group_mbid) {
            Log::warning('MusicBrainz tracklist: Album not found or no release group ID', [
                'albumId' => $this->albumId,
            ]);

            return;
        }

        // Check if we should use an existing selected release
        $useExistingRelease = ! $this->forceReselect && $album->selected_release_mbid;

        if ($useExistingRelease) {
            // Use previously selected release for stability
            $releaseId = $album->selected_release_mbid;

            Log::debug('MusicBrainz: Using existing selected release', [
                'albumId' => $this->albumId,
                'releaseId' => $releaseId,
            ]);
        } else {
            // Step 1: Fetch releases for this release group
            $releases = $this->fetchReleases($album->musicbrainz_release_group_mbid);

            if ($releases === null) {
                // Rate limited, job was released
                return;
            }

            if (empty($releases)) {
                Log::info('MusicBrainz: No releases found for release group', [
                    'albumId' => $this->albumId,
                    'releaseGroupId' => $album->musicbrainz_release_group_mbid,
                ]);

                return;
            }

            // Step 2: Pick the best release
            $bestRelease = $this->pickBestRelease($releases);
            $releaseId = $bestRelease['id'];
        }

        // Step 3: Fetch full tracklist for the selected release
        $media = $this->fetchTracklist($releaseId);

        if ($media === null) {
            // Rate limited, job was released
            return;
        }

        if (empty($media)) {
            Log::warning('MusicBrainz: No media/tracks found in release', [
                'albumId' => $this->albumId,
                'releaseId' => $releaseId,
            ]);

            return;
        }

        // Step 4: Upsert tracks
        $this->upsertTracks($album, $releaseId, $media);
    }

    /**
     * Fetch all releases for a release group.
     *
     * Includes media to get format and track-count for scoring.
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
     * Upsert tracks to the database.
     *
     * Uses atomic transaction to prevent partial deletes if insert fails.
     * Position is computed as a stable numeric value even for vinyl-style
     * track numbers like "A1". Raw number is preserved separately.
     *
     * Also updates the album's selected_release_mbid to track which release
     * was used for tracklist import.
     */
    private function upsertTracks(Album $album, string $releaseId, array $media): void
    {
        $tracks = [];
        $now = now();

        foreach ($media as $medium) {
            $discNumber = $medium['position'] ?? 1;
            $fallbackPosition = 0;

            foreach ($medium['tracks'] ?? [] as $track) {
                $fallbackPosition++;
                $recording = $track['recording'] ?? [];

                // Raw track number from MusicBrainz (may be "A1", "B2", etc.)
                $rawNumber = $track['number'] ?? null;

                // Compute numeric position:
                // 1. Prefer explicit 'position' from MB if present and valid
                // 2. Otherwise use fallback counter (1, 2, 3...) per medium
                $position = $this->parseTrackPosition($track, $fallbackPosition);

                $tracks[] = [
                    'album_id' => $album->id,
                    'musicbrainz_recording_id' => $recording['id'] ?? null,
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

        if (empty($tracks)) {
            Log::warning('MusicBrainz: No tracks parsed from media', [
                'albumId' => $album->id,
                'releaseId' => $releaseId,
            ]);

            return;
        }

        // Atomic: either all tracks are replaced or none are.
        // Upsert keyed on (album_id, disc_number, position) updates existing rows.
        // Also stores which release was selected for tracklist import.
        DB::transaction(function () use ($album, $tracks, $releaseId) {
            // Store the selected release MBID on the album
            $album->update([
                'selected_release_mbid' => $releaseId,
                // If no canonical release MBID set yet, use this one
                'musicbrainz_release_mbid' => $album->musicbrainz_release_mbid ?? $releaseId,
            ]);

            Track::upsert(
                $tracks,
                ['album_id', 'disc_number', 'position'],
                ['musicbrainz_recording_id', 'musicbrainz_release_id', 'title', 'number', 'length_ms', 'source', 'source_last_synced_at', 'updated_at']
            );

            // Remove stale tracks not in the new set
            // Build a set of valid (disc_number, position) pairs
            $validKeys = collect($tracks)
                ->map(fn ($t) => $t['disc_number'].':'.$t['position'])
                ->all();

            Track::where('album_id', $album->id)
                ->get(['id', 'disc_number', 'position'])
                ->filter(fn ($t) => ! in_array($t->disc_number.':'.$t->position, $validKeys))
                ->each(fn ($t) => $t->delete());
        });

        Log::info('MusicBrainz: Tracks saved', [
            'albumId' => $album->id,
            'albumTitle' => $album->title,
            'releaseId' => $releaseId,
            'trackCount' => count($tracks),
        ]);
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
        // This handles vinyl tracks ("A1", "B2") and missing data gracefully
        return $fallback;
    }

    /**
     * Count edition keywords in release title and disambiguation.
     *
     * Checks title and disambiguation fields for keywords that indicate
     * special editions (deluxe, remastered, etc.) which are less desirable
     * than standard releases for tracklist stability.
     */
    private function countEditionKeywords(array $release): int
    {
        $searchText = strtolower(
            ($release['title'] ?? '').' '.($release['disambiguation'] ?? '')
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
