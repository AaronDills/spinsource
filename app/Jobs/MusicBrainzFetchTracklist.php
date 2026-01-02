<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class MusicBrainzFetchTracklist extends MusicBrainzJob implements ShouldBeUnique
{
    public int $uniqueFor = 3600; // 1 hour

    public function __construct(
        public int $albumId,
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

        if (! $album || ! $album->musicbrainz_release_group_id) {
            Log::warning('MusicBrainz tracklist: Album not found or no release group ID', [
                'albumId' => $this->albumId,
            ]);

            return;
        }

        // Step 1: Fetch releases for this release group
        $releases = $this->fetchReleases($album->musicbrainz_release_group_id);

        if ($releases === null) {
            // Rate limited, job was released
            return;
        }

        if (empty($releases)) {
            Log::info('MusicBrainz: No releases found for release group', [
                'albumId' => $this->albumId,
                'releaseGroupId' => $album->musicbrainz_release_group_id,
            ]);

            return;
        }

        // Step 2: Pick the best release
        $bestRelease = $this->pickBestRelease($releases);

        // Step 3: Fetch full tracklist for the best release
        $media = $this->fetchTracklist($bestRelease['id']);

        if ($media === null) {
            // Rate limited, job was released
            return;
        }

        if (empty($media)) {
            Log::warning('MusicBrainz: No media/tracks found in release', [
                'albumId' => $this->albumId,
                'releaseId' => $bestRelease['id'],
            ]);

            return;
        }

        // Step 4: Upsert tracks
        $this->upsertTracks($album, $bestRelease['id'], $media);
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
     * Pick the best release from a list using a scoring algorithm.
     *
     * Criteria (in order of importance):
     * - Official status (+100)
     * - Physical format like CD/Vinyl (+50)
     * - English-speaking country (+30)
     * - Track count (capped at +20)
     * - Release age - older is better (capped at +30)
     * - Has barcode (+10)
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
     */
    private function upsertTracks(Album $album, string $releaseId, array $media): void
    {
        $tracks = [];
        $now = now();

        foreach ($media as $medium) {
            $discNumber = $medium['position'] ?? 1;

            foreach ($medium['tracks'] ?? [] as $track) {
                $recording = $track['recording'] ?? [];

                $tracks[] = [
                    'album_id' => $album->id,
                    'musicbrainz_recording_id' => $recording['id'] ?? null,
                    'musicbrainz_release_id' => $releaseId,
                    'title' => $track['title'] ?? $recording['title'] ?? 'Unknown',
                    'position' => $track['position'] ?? $track['number'] ?? 0,
                    'disc_number' => $discNumber,
                    'length_ms' => $track['length'] ?? $recording['length'] ?? null,
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

        // Delete existing tracks and insert new ones
        Track::where('album_id', $album->id)->delete();
        Track::insert($tracks);

        Log::info('MusicBrainz: Tracks saved', [
            'albumId' => $album->id,
            'albumTitle' => $album->title,
            'releaseId' => $releaseId,
            'trackCount' => count($tracks),
        ]);
    }
}
