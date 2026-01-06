<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Support\Str;

class SeoService
{
    private const MAX_DESCRIPTION_LENGTH = 160;

    private const DEFAULT_OG_IMAGE = '/images/og-default.svg';

    /**
     * Truncate text to a safe length for meta descriptions.
     */
    public static function truncateDescription(?string $text, int $maxLength = self::MAX_DESCRIPTION_LENGTH): ?string
    {
        if (empty($text)) {
            return null;
        }

        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return Str::limit($text, $maxLength - 3, '...');
    }

    /**
     * Build canonical URL without query parameters.
     */
    public static function canonicalUrl(?string $path = null): string
    {
        if ($path !== null) {
            return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
        }

        $url = url()->current();

        // Remove query parameters
        if (($pos = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $pos);
        }

        return $url;
    }

    /**
     * Get the default OG image URL.
     */
    public static function defaultOgImage(): string
    {
        return asset(self::DEFAULT_OG_IMAGE);
    }

    /**
     * Build JSON-LD for an Artist (MusicGroup).
     */
    public static function artistJsonLd(Artist $artist): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicGroup',
            'name' => $artist->name,
            'url' => route('artist.show', $artist),
        ];

        // Image
        if ($artist->image_commons) {
            $data['image'] = 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($artist->image_commons).'?width=600';
        }

        // Description
        if ($artist->description) {
            $data['description'] = self::truncateDescription($artist->description, 300);
        }

        // Genres
        if ($artist->relationLoaded('genres') && $artist->genres->isNotEmpty()) {
            $data['genre'] = $artist->genres->pluck('name')->toArray();
        }

        // sameAs links
        $sameAs = [];

        if ($artist->wikipedia_url) {
            $sameAs[] = $artist->wikipedia_url;
        }

        if ($artist->official_website) {
            $sameAs[] = $artist->official_website;
        }

        if ($artist->spotify_artist_id) {
            $sameAs[] = 'https://open.spotify.com/artist/'.$artist->spotify_artist_id;
        }

        if ($artist->apple_music_artist_id) {
            $sameAs[] = 'https://music.apple.com/artist/'.$artist->apple_music_artist_id;
        }

        if ($artist->musicbrainz_artist_mbid) {
            $sameAs[] = 'https://musicbrainz.org/artist/'.$artist->musicbrainz_artist_mbid;
        }

        if ($artist->discogs_artist_id) {
            $sameAs[] = 'https://www.discogs.com/artist/'.$artist->discogs_artist_id;
        }

        if ($artist->wikidata_qid) {
            $sameAs[] = 'https://www.wikidata.org/wiki/'.$artist->wikidata_qid;
        }

        // Add links from ArtistLink relationship
        if ($artist->relationLoaded('links')) {
            foreach ($artist->links as $link) {
                if (! in_array($link->url, $sameAs)) {
                    $sameAs[] = $link->url;
                }
            }
        }

        if (! empty($sameAs)) {
            $data['sameAs'] = $sameAs;
        }

        // Founding location
        if ($artist->relationLoaded('country') && $artist->country) {
            $data['foundingLocation'] = [
                '@type' => 'Country',
                'name' => $artist->country->name,
            ];
        }

        // Formation date
        if ($artist->formed_year) {
            $data['foundingDate'] = (string) $artist->formed_year;
        }

        // Dissolution date
        if ($artist->disbanded_year) {
            $data['dissolutionDate'] = (string) $artist->disbanded_year;
        }

        return $data;
    }

    /**
     * Build JSON-LD for an Album (MusicAlbum).
     */
    public static function albumJsonLd(Album $album): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicAlbum',
            'name' => $album->title,
            'url' => route('album.show', $album),
        ];

        // Image
        if ($album->cover_image_url) {
            $data['image'] = $album->cover_image_url;
        }

        // Description
        if ($album->description) {
            $data['description'] = self::truncateDescription($album->description, 300);
        }

        // Artist
        if ($album->relationLoaded('artist') && $album->artist) {
            $data['byArtist'] = [
                '@type' => 'MusicGroup',
                'name' => $album->artist->name,
                'url' => route('artist.show', $album->artist),
            ];
        }

        // Release date
        if ($album->release_date) {
            $data['datePublished'] = $album->release_date->format('Y-m-d');
        } elseif ($album->release_year) {
            $data['datePublished'] = (string) $album->release_year;
        }

        // Album type mapping
        $albumTypeMap = [
            'album' => 'AlbumRelease',
            'ep' => 'EPRelease',
            'single' => 'SingleRelease',
            'compilation' => 'CompilationAlbum',
            'live' => 'LiveAlbum',
            'soundtrack' => 'SoundtrackAlbum',
        ];

        if ($album->album_type && isset($albumTypeMap[$album->album_type])) {
            $data['albumReleaseType'] = 'https://schema.org/'.$albumTypeMap[$album->album_type];
        }

        // Tracks
        if ($album->relationLoaded('tracks') && $album->tracks->isNotEmpty()) {
            $data['numTracks'] = $album->tracks->count();

            $tracks = [];
            foreach ($album->tracks as $track) {
                $trackData = [
                    '@type' => 'MusicRecording',
                    'name' => $track->title,
                    'position' => $track->position,
                ];

                if ($track->length_ms) {
                    // ISO 8601 duration format
                    $totalSeconds = (int) ($track->length_ms / 1000);
                    $minutes = (int) ($totalSeconds / 60);
                    $seconds = $totalSeconds % 60;
                    $trackData['duration'] = sprintf('PT%dM%dS', $minutes, $seconds);
                }

                $tracks[] = $trackData;
            }

            $data['track'] = [
                '@type' => 'ItemList',
                'numberOfItems' => count($tracks),
                'itemListElement' => $tracks,
            ];
        }

        // sameAs links
        $sameAs = [];

        if ($album->wikipedia_url) {
            $sameAs[] = $album->wikipedia_url;
        }

        if ($album->spotify_album_id) {
            $sameAs[] = 'https://open.spotify.com/album/'.$album->spotify_album_id;
        }

        if ($album->apple_music_album_id) {
            $sameAs[] = 'https://music.apple.com/album/'.$album->apple_music_album_id;
        }

        if ($album->musicbrainz_release_group_mbid) {
            $sameAs[] = 'https://musicbrainz.org/release-group/'.$album->musicbrainz_release_group_mbid;
        }

        if ($album->wikidata_qid) {
            $sameAs[] = 'https://www.wikidata.org/wiki/'.$album->wikidata_qid;
        }

        if (! empty($sameAs)) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * Build JSON-LD for WebSite (for homepage).
     */
    public static function websiteJsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name', 'Spinsearch'),
            'url' => config('app.url'),
            'description' => 'A music encyclopedia for the curious listener. Explore complete discographies, discover artist histories, and navigate connections between albums, genres, and eras.',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('search.results').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Encode JSON-LD for safe HTML output.
     */
    public static function encodeJsonLd(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }
}
