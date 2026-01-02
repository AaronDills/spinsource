<?php

namespace App\Jobs;

use App\Enums\ArtistLinkType;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enrich artist data from Wikidata using three separate, simplified SPARQL queries.
 *
 * The original single complex query with 20+ OPTIONAL clauses and aggregations
 * frequently timed out (HTTP 504) on WDQS. This version splits into:
 * 1. Basic artist data (name, description, country, dates, MBID, images)
 * 2. Genres (simple artist->genre relationships)
 * 3. Social/streaming links (external IDs)
 *
 * Each query is simpler and faster, reducing timeout risk.
 */
class WikidataEnrichArtists extends WikidataJob
{
    // Increased timeout for 3 sequential queries
    public int $timeout = 300;

    /** @param array<int,string> $artistQids */
    public function __construct(public array $artistQids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $artistQids = array_values(array_unique(array_filter(
            $this->artistQids,
            fn ($q) => is_string($q) && preg_match('/^Q\d+$/', $q)
        )));

        if (count($artistQids) === 0) {
            return;
        }

        Log::info('Wikidata artist enrich batch start (split queries)', [
            'count' => count($artistQids),
            'sample' => array_slice($artistQids, 0, 5),
        ]);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        // 1. Fetch basic artist data
        $basicData = $this->fetchBasicData($values);
        if ($basicData === null) {
            return; // Rate limited or error, job released
        }

        // Small delay between queries to be gentle on WDQS
        usleep(500_000); // 500ms

        // 2. Fetch genres
        $genreData = $this->fetchGenres($values);
        if ($genreData === null) {
            return;
        }

        usleep(500_000);

        // 3. Fetch social/streaming links
        $linkData = $this->fetchLinks($values);
        if ($linkData === null) {
            return;
        }

        // Merge and process all data
        $this->processResults($artistQids, $basicData, $genreData, $linkData);
    }

    /**
     * Fetch basic artist data (name, description, country, dates, etc.)
     * Optimized query uses isHuman boolean instead of instanceOf list
     */
    private function fetchBasicData(string $values): ?array
    {
        $sparql = DataSourceQuery::get('artist_enrich_basic', 'wikidata', ['values' => $values]);
        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return null;
        }

        $bindings = $response->json('results.bindings', []);

        // Simple mapping - one row per artist with optimized query
        $result = [];
        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $qid) {
                continue;
            }

            // Skip if already processed (shouldn't happen with optimized query)
            if (isset($result[$qid])) {
                continue;
            }

            // isHuman is a boolean from BIND(EXISTS {...})
            $isHuman = data_get($row, 'isHuman.value') === 'true';

            $result[$qid] = [
                'artistLabel' => data_get($row, 'artistLabel.value'),
                'artistDescription' => data_get($row, 'artistDescription.value'),
                'isHuman' => $isHuman,
                'country' => $this->qidFromEntityUrl(data_get($row, 'country.value')),
                'countryLabel' => data_get($row, 'countryLabel.value'),
                'givenNameLabel' => data_get($row, 'givenNameLabel.value'),
                'familyNameLabel' => data_get($row, 'familyNameLabel.value'),
                'officialWebsite' => data_get($row, 'officialWebsite.value'),
                'imageCommons' => data_get($row, 'imageCommons.value'),
                'logoCommons' => data_get($row, 'logoCommons.value'),
                'commonsCategory' => data_get($row, 'commonsCategory.value'),
                'formed' => data_get($row, 'formed.value'),
                'disbanded' => data_get($row, 'disbanded.value'),
                'musicBrainzId' => data_get($row, 'musicBrainzId.value'),
            ];
        }

        return $result;
    }

    /**
     * Fetch artist genre relationships
     */
    private function fetchGenres(string $values): ?array
    {
        $sparql = DataSourceQuery::get('artist_enrich_genres', 'wikidata', ['values' => $values]);
        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return null;
        }

        $bindings = $response->json('results.bindings', []);

        // Group genres by artist QID
        $result = [];
        foreach ($bindings as $row) {
            $artistQid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));

            if ($artistQid && $genreQid) {
                if (! isset($result[$artistQid])) {
                    $result[$artistQid] = [];
                }
                $result[$artistQid][] = $genreQid;
            }
        }

        // Dedupe
        foreach ($result as $qid => $genres) {
            $result[$qid] = array_values(array_unique($genres));
        }

        return $result;
    }

    /**
     * Fetch artist social/streaming links
     */
    private function fetchLinks(string $values): ?array
    {
        $sparql = DataSourceQuery::get('artist_enrich_links', 'wikidata', ['values' => $values]);
        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return null;
        }

        $bindings = $response->json('results.bindings', []);

        // Group by artist QID, collecting all link values
        $result = [];
        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $qid) {
                continue;
            }

            if (! isset($result[$qid])) {
                $result[$qid] = [
                    'twitter' => null,
                    'instagram' => null,
                    'facebook' => null,
                    'youtubeChannel' => null,
                    'spotifyArtistId' => null,
                    'appleMusicArtistId' => null,
                    'discogsArtistId' => null,
                    'deezerArtistId' => null,
                    'soundcloudId' => null,
                    'bandcampId' => null,
                    'subreddit' => null,
                ];
            }

            // Fill in values (first wins)
            foreach (array_keys($result[$qid]) as $field) {
                if ($result[$qid][$field] === null && data_get($row, "{$field}.value") !== null) {
                    $result[$qid][$field] = data_get($row, "{$field}.value");
                }
            }
        }

        return $result;
    }

    /**
     * Process and persist all fetched data
     */
    private function processResults(array $artistQids, array $basicData, array $genreData, array $linkData): void
    {
        $now = now();

        // Bulk staging
        $countriesToUpsert = [];
        $artistRows = [];
        $artistMeta = [];
        $allGenreQids = [];

        foreach ($artistQids as $qid) {
            $basic = $basicData[$qid] ?? null;
            if (! $basic) {
                continue;
            }

            $name = $basic['artistLabel'] ?: $qid;

            // Skip artists that still have Q-ID as name (no label in Wikidata)
            if (preg_match('/^Q\d+$/', $name)) {
                continue;
            }

            // Determine type from isHuman boolean
            $artistType = ($basic['isHuman'] ?? false) ? 'person' : 'group';

            $countryQid = $basic['country'];
            $countryName = $basic['countryLabel'];

            if ($countryQid && $countryName && ! preg_match('/^Q\d+$/', $countryName)) {
                $countriesToUpsert[$countryQid] = $countryName;
            }

            $formedYear = $this->extractYear($basic['formed'] ?? null);
            $disbandedYear = $this->extractYear($basic['disbanded'] ?? null);

            // Compute sort name using given/family names when available
            $sortName = $this->computeSortName(
                $name,
                $basic['givenNameLabel'] ?? null,
                $basic['familyNameLabel'] ?? null
            );

            $imageCommons = $this->commonsFilename($basic['imageCommons'] ?? null);
            $logoCommons = $this->commonsFilename($basic['logoCommons'] ?? null);

            // Extract external IDs from link data for direct column storage
            $artistLinkData = $linkData[$qid] ?? [];

            $artistRows[] = [
                'wikidata_qid' => $qid,
                'name' => $name,
                'sort_name' => $sortName,
                'artist_type' => $artistType,
                'musicbrainz_artist_mbid' => $basic['musicBrainzId'] ?? null,
                'spotify_artist_id' => $artistLinkData['spotifyArtistId'] ?? null,
                'apple_music_artist_id' => $artistLinkData['appleMusicArtistId'] ?? null,
                'discogs_artist_id' => $artistLinkData['discogsArtistId'] ?? null,
                'description' => $basic['artistDescription'] ?? null,
                'official_website' => $basic['officialWebsite'] ?? null,
                'image_commons' => $imageCommons,
                'logo_commons' => $logoCommons,
                'commons_category' => $basic['commonsCategory'] ?? null,
                'formed_year' => $formedYear,
                'disbanded_year' => $disbandedYear,
                '__country_qid' => $countryQid,
                'source' => 'wikidata',
                'source_last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Genres from separate query
            $genreQids = $genreData[$qid] ?? [];
            $allGenreQids = array_merge($allGenreQids, $genreQids);

            // Links from separate query
            $links = $this->buildLinksFromData($basic, $linkData[$qid] ?? []);

            $artistMeta[$qid] = [
                'countryQid' => $countryQid,
                'genreQids' => $genreQids,
                'links' => $links,
            ];
        }

        if (empty($artistRows)) {
            Log::info('Wikidata artist enrich batch done (no valid data)', [
                'count' => count($artistQids),
            ]);

            return;
        }

        // Upsert Countries in bulk
        if (! empty($countriesToUpsert)) {
            $countryRows = [];
            foreach ($countriesToUpsert as $cqid => $nm) {
                $countryRows[] = [
                    'wikidata_id' => $cqid,
                    'name' => $nm,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            Country::upsert($countryRows, ['wikidata_id'], ['name', 'updated_at']);
        }

        $countriesByQid = ! empty($countriesToUpsert)
            ? Country::query()
                ->whereIn('wikidata_id', array_keys($countriesToUpsert))
                ->get(['id', 'wikidata_id'])
                ->keyBy('wikidata_id')
            : collect();

        // Replace __country_qid with country_id
        foreach ($artistRows as &$r) {
            $cq = $r['__country_qid'] ?? null;
            unset($r['__country_qid']);
            $r['country_id'] = $cq && $countriesByQid->has($cq) ? $countriesByQid->get($cq)->id : null;
        }
        unset($r);

        // Upsert Artists in bulk
        Artist::upsert(
            $artistRows,
            ['wikidata_qid'],
            [
                'name',
                'sort_name',
                'artist_type',
                'musicbrainz_artist_mbid',
                'spotify_artist_id',
                'apple_music_artist_id',
                'discogs_artist_id',
                'description',
                'official_website',
                'image_commons',
                'logo_commons',
                'commons_category',
                'formed_year',
                'disbanded_year',
                'country_id',
                'source',
                'source_last_synced_at',
                'updated_at',
            ]
        );

        // Load artist IDs for pivot/link inserts
        $artistsByQid = Artist::query()
            ->whereIn('wikidata_qid', array_keys($artistMeta))
            ->get(['id', 'wikidata_qid'])
            ->keyBy('wikidata_qid');

        // Map genres
        $allGenreQids = array_values(array_unique($allGenreQids));
        $genresByQid = ! empty($allGenreQids)
            ? Genre::query()->whereIn('wikidata_qid', $allGenreQids)->get(['id', 'wikidata_qid'])->keyBy('wikidata_qid')
            : collect();

        // Build bulk pivot/link rows
        $pivotRows = [];
        $linkRows = [];

        foreach ($artistMeta as $qid => $meta) {
            $artist = $artistsByQid->get($qid);
            if (! $artist) {
                continue;
            }

            foreach ($meta['genreQids'] as $gqid) {
                $genre = $genresByQid->get($gqid);
                if (! $genre) {
                    continue;
                }

                $pivotRows[] = [
                    'artist_id' => $artist->id,
                    'genre_id' => $genre->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($meta['links'] as $lnk) {
                $url = $this->normalizeUrl($lnk['url']);
                if (! $url) {
                    continue;
                }

                $linkRows[] = [
                    'artist_id' => $artist->id,
                    'type' => $lnk['type'],
                    'url' => $url,
                    'source' => 'wikidata',
                    'is_official' => (bool) $lnk['is_official'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $pivotInserted = 0;
        if (! empty($pivotRows)) {
            $pivotInserted = DB::table('artist_genre')->insertOrIgnore($pivotRows);
        }

        $linksInserted = 0;
        if (! empty($linkRows)) {
            $linksInserted = ArtistLink::insertOrIgnore($linkRows);
        }

        Log::info('Wikidata artist enrich batch done (split queries)', [
            'requested' => count($artistQids),
            'artistsUpserted' => count($artistRows),
            'pivotInserted' => $pivotInserted,
            'linksInserted' => $linksInserted,
        ]);
    }

    /**
     * Compute a sortable name from display name and name components.
     * For persons with family name: "Family, Given"
     * For groups/bands: strip "The " prefix and lowercase
     */
    private function computeSortName(?string $displayName, ?string $givenName, ?string $familyName): ?string
    {
        $displayName = $displayName ? trim($displayName) : null;
        if (! $displayName) {
            return null;
        }

        $givenName = $givenName ? trim($givenName) : null;
        $familyName = $familyName ? trim($familyName) : null;

        // Skip Q-ID labels that leaked through
        if ($givenName && preg_match('/^Q\d+$/', $givenName)) {
            $givenName = null;
        }
        if ($familyName && preg_match('/^Q\d+$/', $familyName)) {
            $familyName = null;
        }

        // If we have family name, format as "Family, Given"
        if ($familyName) {
            return $givenName ? "{$familyName}, {$givenName}" : $familyName;
        }

        // For groups/unknowns: strip "The " prefix and lowercase for sorting
        $sortName = mb_strtolower($displayName);
        if (str_starts_with($sortName, 'the ')) {
            $sortName = trim(substr($sortName, 4));
        }

        return $sortName;
    }

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

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }

    /**
     * Build canonical link URLs from basic data and link data
     */
    private function buildLinksFromData(array $basic, array $linkData): array
    {
        $links = [];

        // Website from basic data
        if ($v = $basic['officialWebsite'] ?? null) {
            $links[] = ['type' => ArtistLinkType::WEBSITE->value, 'url' => $v, 'is_official' => true];
        }

        // Social/streaming from link data
        if ($v = $linkData['twitter'] ?? null) {
            $links[] = ['type' => ArtistLinkType::TWITTER->value, 'url' => "https://twitter.com/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['instagram'] ?? null) {
            $links[] = ['type' => ArtistLinkType::INSTAGRAM->value, 'url' => "https://www.instagram.com/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['facebook'] ?? null) {
            $links[] = ['type' => ArtistLinkType::FACEBOOK->value, 'url' => "https://www.facebook.com/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['youtubeChannel'] ?? null) {
            $links[] = ['type' => ArtistLinkType::YOUTUBE->value, 'url' => "https://www.youtube.com/channel/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['spotifyArtistId'] ?? null) {
            $links[] = ['type' => ArtistLinkType::SPOTIFY->value, 'url' => "https://open.spotify.com/artist/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['appleMusicArtistId'] ?? null) {
            $links[] = ['type' => ArtistLinkType::APPLE_MUSIC->value, 'url' => "https://music.apple.com/artist/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['deezerArtistId'] ?? null) {
            $links[] = ['type' => ArtistLinkType::DEEZER->value, 'url' => "https://www.deezer.com/artist/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['soundcloudId'] ?? null) {
            $links[] = ['type' => ArtistLinkType::SOUNDCLOUD->value, 'url' => "https://soundcloud.com/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['bandcampId'] ?? null) {
            $links[] = ['type' => ArtistLinkType::BANDCAMP->value, 'url' => "https://bandcamp.com/{$v}", 'is_official' => true];
        }
        if ($v = $linkData['subreddit'] ?? null) {
            $links[] = ['type' => ArtistLinkType::REDDIT->value, 'url' => "https://www.reddit.com/r/{$v}", 'is_official' => true];
        }

        return $links;
    }
}
