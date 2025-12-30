<?php

namespace App\Jobs;

use App\Enums\ArtistLinkType;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\Genre;
use App\Support\Sparql;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikidataEnrichArtists implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [5, 15, 45, 120, 300];

    /** @param array<int,string> $artistQids */
    public function __construct(public array $artistQids = [])
    {
    }

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        $artistQids = array_values(array_unique(array_filter(
            $this->artistQids,
            fn ($q) => is_string($q) && preg_match('/^Q\d+$/', $q)
        )));

        if (count($artistQids) === 0) {
            return;
        }

        Log::info('Wikidata artist enrich batch start', [
            'count'  => count($artistQids),
            'sample' => array_slice($artistQids, 0, 5),
        ]);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        // Uses the aggregated one-row-per-artist SPARQL:
        // resources/sparql/artist_enrich_agg.sparql
        $sparql = Sparql::load('artist_enrich_agg', [
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
            Log::warning('Wikidata artist enrich request failed', [
                'count'   => count($artistQids),
                'status'  => optional($e->response)->status(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $bindings = $response->json('results.bindings', []);
        if (empty($bindings)) {
            Log::info('Wikidata artist enrich batch done (no rows)', [
                'count' => count($artistQids),
            ]);
            return;
        }

        $now = now();

        // Bulk staging
        $countriesToUpsert = [];   // [countryQid => countryName]
        $artistRows = [];          // for Artist::upsert
        $artistMeta = [];          // [artistQid => ['genreQids'=>[], 'links'=>[], 'countryQid'=>?string]]
        $allGenreQids = [];

        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $qid) continue;

            $name = data_get($row, 'artistLabel.value') ?: $qid; // name is required in schema

            // Determine type: person if instanceOf includes Q5 (human), else group
            $instanceOfStr = (string) data_get($row, 'instanceOf.value', '');
            $instanceOfUrls = $instanceOfStr !== '' ? explode('|', $instanceOfStr) : [];
            $artistType = in_array('http://www.wikidata.org/entity/Q5', $instanceOfUrls, true) ? 'person' : 'group';

            $countryQid  = $this->qidFromEntityUrl(data_get($row, 'country.value'));
            $countryName = data_get($row, 'countryLabel.value');

            if ($countryQid && $countryName) {
                $countriesToUpsert[$countryQid] = $countryName;
            }

            $formedYear    = $this->extractYear(data_get($row, 'formed.value'));
            $disbandedYear = $this->extractYear(data_get($row, 'disbanded.value'));

            $given  = data_get($row, 'givenNameLabel.value');
            $family = data_get($row, 'familyNameLabel.value');
            $sortName = $this->computeSortName($name, $given, $family);

            $imageCommons = $this->commonsFilename(data_get($row, 'imageCommons.value'));
            $logoCommons  = $this->commonsFilename(data_get($row, 'logoCommons.value'));

            $artistRows[] = [
                'wikidata_id'      => $qid,
                'name'             => $name,
                'sort_name'        => $sortName,
                'artist_type'      => $artistType,
                'musicbrainz_id'   => data_get($row, 'musicBrainzId.value'),
                'description'      => data_get($row, 'artistDescription.value'),
                'wikipedia_url'    => data_get($row, 'wikipediaUrl.value'),
                'official_website' => data_get($row, 'officialWebsite.value'),
                'image_commons'    => $imageCommons,
                'logo_commons'     => $logoCommons,
                'commons_category' => data_get($row, 'commonsCategory.value'),
                'formed_year'      => $formedYear,
                'disbanded_year'   => $disbandedYear,
                // temporarily store country QID; we’ll map to country_id after upsert
                '__country_qid'     => $countryQid,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            // Genres: aggregated string of entity URLs separated by "|"
            $genreStr = (string) data_get($row, 'genre.value', '');
            $genreUrls = $genreStr !== '' ? explode('|', $genreStr) : [];
            $genreQids = array_values(array_unique(array_filter(array_map([$this, 'qidFromEntityUrl'], $genreUrls))));
            $allGenreQids = array_merge($allGenreQids, $genreQids);

            // Links
            $links = $this->buildLinksFromRow($row);

            $artistMeta[$qid] = [
                'countryQid' => $countryQid,
                'genreQids'  => $genreQids,
                'links'      => $links,
            ];
        }

        // Upsert Countries in bulk (by wikidata_id)
        if (!empty($countriesToUpsert)) {
            $countryRows = [];
            foreach ($countriesToUpsert as $qid => $nm) {
                $countryRows[] = [
                    'wikidata_id' => $qid,
                    'name'        => $nm,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            Country::upsert($countryRows, ['wikidata_id'], ['name', 'updated_at']);
        }

        $countriesByQid = !empty($countriesToUpsert)
            ? Country::query()
                ->whereIn('wikidata_id', array_keys($countriesToUpsert))
                ->get(['id', 'wikidata_id'])
                ->keyBy('wikidata_id')
            : collect();

        // Replace __country_qid with country_id for artist upsert
        foreach ($artistRows as &$r) {
            $cq = $r['__country_qid'] ?? null;
            unset($r['__country_qid']);
            $r['country_id'] = $cq && $countriesByQid->has($cq) ? $countriesByQid->get($cq)->id : null;
        }
        unset($r);

        // Upsert Artists in bulk (conflict target: wikidata_id)
        Artist::upsert(
            $artistRows,
            ['wikidata_id'],
            [
                'name',
                'sort_name',
                'artist_type',
                'musicbrainz_id',
                'description',
                'wikipedia_url',
                'official_website',
                'image_commons',
                'logo_commons',
                'commons_category',
                'formed_year',
                'disbanded_year',
                'country_id',
                'updated_at',
            ]
        );

        // Load artist IDs for pivot/link inserts
        $artistsByQid = Artist::query()
            ->whereIn('wikidata_id', array_keys($artistMeta))
            ->get(['id', 'wikidata_id'])
            ->keyBy('wikidata_id');

        // Map genres (only link ones you already have in your genres table)
        $allGenreQids = array_values(array_unique($allGenreQids));
        $genresByQid = !empty($allGenreQids)
            ? Genre::query()->whereIn('wikidata_id', $allGenreQids)->get(['id', 'wikidata_id'])->keyBy('wikidata_id')
            : collect();

        // Build bulk pivot/link rows
        $pivotRows = [];
        $linkRows  = [];

        foreach ($artistMeta as $qid => $meta) {
            $artist = $artistsByQid->get($qid);
            if (! $artist) continue;

            // Pivot rows (artist_genre)
            foreach ($meta['genreQids'] as $gqid) {
                $genre = $genresByQid->get($gqid);
                if (! $genre) continue;

                $pivotRows[] = [
                    'artist_id'  => $artist->id,
                    'genre_id'   => $genre->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Link rows (artist_links)
            foreach ($meta['links'] as $lnk) {
                $url = $this->normalizeUrl($lnk['url']);
                if (! $url) continue;

                $linkRows[] = [
                    'artist_id'   => $artist->id,
                    'type'        => $lnk['type'],
                    'url'         => $url,
                    'source'      => 'wikidata',
                    'is_official' => (bool) $lnk['is_official'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        // Bulk insert pivot + links (deduped by your unique constraints)
        $pivotInserted = 0;
        if (!empty($pivotRows)) {
            $pivotInserted = DB::table('artist_genre')->insertOrIgnore($pivotRows);
        }

        $linksInserted = 0;
        if (!empty($linkRows)) {
            $linksInserted = ArtistLink::insertOrIgnore($linkRows);
        }

        Log::info('Wikidata artist enrich batch done', [
            'requested'       => count($artistQids),
            'rows'            => count($bindings),
            'artistsUpserted' => count($artistRows),
            'pivotInserted'   => $pivotInserted,
            'linksInserted'   => $linksInserted,
        ]);
    }

    private function qidFromEntityUrl(?string $url): ?string
    {
        if (! $url) return null;
        $pos = strrpos($url, '/');
        if ($pos === false) return null;
        $qid = substr($url, $pos + 1);
        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
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

    private function computeSortName(string $displayName, ?string $given, ?string $family): string
    {
        $given = $given ? trim($given) : null;
        $family = $family ? trim($family) : null;

        if ($family) {
            if ($given) return "{$family}, {$given}";
            return $family;
        }

        return trim($displayName);
    }

    private function commonsFilename(?string $value): ?string
    {
        if (! $value) return null;

        $value = trim($value);

        if (str_contains($value, 'Special:FilePath/')) {
            $value = substr($value, strrpos($value, 'Special:FilePath/') + strlen('Special:FilePath/'));
        } else {
            $slash = strrpos($value, '/');
            if ($slash !== false) $value = substr($value, $slash + 1);
        }

        $value = urldecode($value);
        return $value !== '' ? $value : null;
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) return null;
        $url = trim($url);
        if ($url === '') return null;
        if (! preg_match('#^https?://#i', $url)) return null;
        return $url;
    }

    /**
     * Build canonical link URLs from a single aggregated row.
     * Returns array<array{type:string,url:string,is_official:bool}>
     */
    private function buildLinksFromRow(array $row): array
    {
        $links = [];

        // Website (P856)
        if ($v = data_get($row, 'officialWebsite.value')) {
            $links[] = ['type' => ArtistLinkType::WEBSITE->value, 'url' => $v, 'is_official' => true];
        }

        // Twitter (P2002)
        if ($v = data_get($row, 'twitter.value')) {
            $links[] = ['type' => ArtistLinkType::TWITTER->value, 'url' => "https://twitter.com/{$v}", 'is_official' => true];
        }

        // Instagram (P2003)
        if ($v = data_get($row, 'instagram.value')) {
            $links[] = ['type' => ArtistLinkType::INSTAGRAM->value, 'url' => "https://www.instagram.com/{$v}", 'is_official' => true];
        }

        // Facebook (P2013)
        if ($v = data_get($row, 'facebook.value')) {
            $links[] = ['type' => ArtistLinkType::FACEBOOK->value, 'url' => "https://www.facebook.com/{$v}", 'is_official' => true];
        }

        // YouTube channel (P2397)
        if ($v = data_get($row, 'youtubeChannel.value')) {
            $links[] = ['type' => ArtistLinkType::YOUTUBE->value, 'url' => "https://www.youtube.com/channel/{$v}", 'is_official' => true];
        }

        // Spotify artist (P1902)
        if ($v = data_get($row, 'spotifyArtistId.value')) {
            $links[] = ['type' => ArtistLinkType::SPOTIFY->value, 'url' => "https://open.spotify.com/artist/{$v}", 'is_official' => true];
        }

        // Apple Music (P2850)
        if ($v = data_get($row, 'appleMusicArtistId.value')) {
            $links[] = ['type' => ArtistLinkType::APPLE_MUSIC->value, 'url' => "https://music.apple.com/artist/{$v}", 'is_official' => true];
        }

        // Deezer (P2722) - requires enum case DEEZER = 'deezer'
        if ($v = data_get($row, 'deezerArtistId.value')) {
            $links[] = ['type' => ArtistLinkType::DEEZER->value, 'url' => "https://www.deezer.com/artist/{$v}", 'is_official' => true];
        }

        // SoundCloud (P3040)
        if ($v = data_get($row, 'soundcloudId.value')) {
            $links[] = ['type' => ArtistLinkType::SOUNDCLOUD->value, 'url' => "https://soundcloud.com/{$v}", 'is_official' => true];
        }

        // Bandcamp (P3283)
        if ($v = data_get($row, 'bandcampId.value')) {
            $links[] = ['type' => ArtistLinkType::BANDCAMP->value, 'url' => "https://bandcamp.com/{$v}", 'is_official' => true];
        }

        // Subreddit (P3984)
        if ($v = data_get($row, 'subreddit.value')) {
            $links[] = ['type' => ArtistLinkType::REDDIT->value, 'url' => "https://www.reddit.com/r/{$v}", 'is_official' => true];
        }

        return $links;
    }
}
