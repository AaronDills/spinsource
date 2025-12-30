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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikidataEnrichArtists implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public array $backoff = [5, 15, 45, 120, 300];

    /**
     * @param array<int, string> $artistQids
     */
    public function __construct(public array $artistQids) {}

    public function handle(): void
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        $artistQids = array_values(array_unique(array_filter($this->artistQids)));
        if (count($artistQids) === 0) {
            return;
        }

        Log::info('Wikidata artist enrich batch start', [
            'count' => count($artistQids),
            'sample' => array_slice($artistQids, 0, 5),
        ]);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        $sparql = Sparql::load('artist_enrich', [
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
                'count' => count($artistQids),
                'status' => optional($e->response)->status(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $bindings = $response->json('results.bindings', []);
        if (count($bindings) === 0) {
            Log::info('Wikidata artist enrich batch returned no rows', [
                'count' => count($artistQids),
            ]);
            return;
        }

        $byArtist = [];
        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (! $qid) continue;

            $byArtist[$qid] ??= [
                'qid' => $qid,
                'name' => null,
                'description' => null,

                'instanceOfQids' => [],

                'countryQid' => null,
                'countryName' => null,
                'officialWebsite' => null,
                'wikipediaUrl' => null,

                'imageCommons' => null,
                'logoCommons' => null,
                'commonsCategory' => null,

                'formedYear' => null,
                'disbandedYear' => null,

                'musicBrainzId' => null,
                'givenName' => null,
                'familyName' => null,

                'genreQids' => [],

                // link fields
                'twitter' => null,
                'instagram' => null,
                'facebook' => null,
                'youtubeChannel' => null,
                'spotifyArtistId' => null,
                'appleMusicArtistId' => null,
                'deezerArtistId' => null,
                'soundcloudId' => null,
                'bandcampId' => null,
                'subreddit' => null,
            ];

            $byArtist[$qid]['name'] = $byArtist[$qid]['name'] ?? data_get($row, 'artistLabel.value');
            $byArtist[$qid]['description'] = $byArtist[$qid]['description'] ?? data_get($row, 'artistDescription.value');

            $instQid = $this->qidFromEntityUrl(data_get($row, 'instanceOf.value'));
            if ($instQid) {
                $byArtist[$qid]['instanceOfQids'][] = $instQid;
            }

            $countryQid = $this->qidFromEntityUrl(data_get($row, 'country.value'));
            if ($countryQid) {
                $byArtist[$qid]['countryQid'] = $countryQid;
                $byArtist[$qid]['countryName'] = $byArtist[$qid]['countryName'] ?? data_get($row, 'countryLabel.value');
            }

            $byArtist[$qid]['officialWebsite'] = $byArtist[$qid]['officialWebsite'] ?? data_get($row, 'officialWebsite.value');
            $byArtist[$qid]['wikipediaUrl'] = $byArtist[$qid]['wikipediaUrl'] ?? data_get($row, 'wikipediaUrl.value');

            $byArtist[$qid]['imageCommons'] = $byArtist[$qid]['imageCommons'] ?? $this->commonsFilename(data_get($row, 'imageCommons.value'));
            $byArtist[$qid]['logoCommons'] = $byArtist[$qid]['logoCommons'] ?? $this->commonsFilename(data_get($row, 'logoCommons.value'));
            $byArtist[$qid]['commonsCategory'] = $byArtist[$qid]['commonsCategory'] ?? data_get($row, 'commonsCategory.value');

            $byArtist[$qid]['formedYear'] = $byArtist[$qid]['formedYear'] ?? $this->extractYear(data_get($row, 'formed.value'));
            $byArtist[$qid]['disbandedYear'] = $byArtist[$qid]['disbandedYear'] ?? $this->extractYear(data_get($row, 'disbanded.value'));

            $byArtist[$qid]['musicBrainzId'] = $byArtist[$qid]['musicBrainzId'] ?? data_get($row, 'musicBrainzId.value');

            $byArtist[$qid]['givenName'] = $byArtist[$qid]['givenName'] ?? data_get($row, 'givenNameLabel.value');
            $byArtist[$qid]['familyName'] = $byArtist[$qid]['familyName'] ?? data_get($row, 'familyNameLabel.value');

            $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
            if ($genreQid) {
                $byArtist[$qid]['genreQids'][] = $genreQid;
            }

            // socials / ids
            $byArtist[$qid]['twitter'] = $byArtist[$qid]['twitter'] ?? data_get($row, 'twitter.value');
            $byArtist[$qid]['instagram'] = $byArtist[$qid]['instagram'] ?? data_get($row, 'instagram.value');
            $byArtist[$qid]['facebook'] = $byArtist[$qid]['facebook'] ?? data_get($row, 'facebook.value');
            $byArtist[$qid]['youtubeChannel'] = $byArtist[$qid]['youtubeChannel'] ?? data_get($row, 'youtubeChannel.value');
            $byArtist[$qid]['spotifyArtistId'] = $byArtist[$qid]['spotifyArtistId'] ?? data_get($row, 'spotifyArtistId.value');
            $byArtist[$qid]['appleMusicArtistId'] = $byArtist[$qid]['appleMusicArtistId'] ?? data_get($row, 'appleMusicArtistId.value');
            $byArtist[$qid]['deezerArtistId'] = $byArtist[$qid]['deezerArtistId'] ?? data_get($row, 'deezerArtistId.value');
            $byArtist[$qid]['soundcloudId'] = $byArtist[$qid]['soundcloudId'] ?? data_get($row, 'soundcloudId.value');
            $byArtist[$qid]['bandcampId'] = $byArtist[$qid]['bandcampId'] ?? data_get($row, 'bandcampId.value');
            $byArtist[$qid]['subreddit'] = $byArtist[$qid]['subreddit'] ?? data_get($row, 'subreddit.value');
        }

        // Preload genres by wikidata_id for pivots
        $allGenreQids = [];
        foreach ($byArtist as $data) {
            $allGenreQids = array_merge($allGenreQids, $data['genreQids']);
        }
        $allGenreQids = array_values(array_unique($allGenreQids));

        $genresByQid = Genre::query()
            ->whereIn('wikidata_id', $allGenreQids)
            ->get(['id', 'wikidata_id'])
            ->keyBy('wikidata_id');

        $upserted = 0;
        $attached = 0;
        $linksTouched = 0;

        foreach ($byArtist as $data) {
            $countryId = null;

            if ($data['countryQid'] && $data['countryName']) {
                $country = Country::updateOrCreate(
                    ['wikidata_id' => $data['countryQid']],
                    ['name' => $data['countryName']]
                );
                $countryId = $country->id;
            }

            $artistType = $this->inferArtistType($data['instanceOfQids']);

            $payload = array_filter([
                'name'             => $data['name'],
                'sort_name'        => $this->computeSortName($data['name'], $data['givenName'], $data['familyName']),
                'artist_type'      => $artistType,
                'musicbrainz_id'   => $data['musicBrainzId'],
                'description'      => $data['description'],
                'wikipedia_url'    => $data['wikipediaUrl'],
                'official_website' => $data['officialWebsite'],
                'image_commons'    => $data['imageCommons'],
                'logo_commons'     => $data['logoCommons'],
                'commons_category' => $data['commonsCategory'],
                'formed_year'      => $data['formedYear'],
                'disbanded_year'   => $data['disbandedYear'],
                'country_id'       => $countryId,
            ], static fn ($v) => $v !== null && $v !== '');

            $artist = Artist::updateOrCreate(
                ['wikidata_id' => $data['qid']],
                $payload
            );
            $upserted++;

            // Pivot: artist_genre
            $genreIds = [];
            foreach (array_unique($data['genreQids']) as $gqid) {
                $g = $genresByQid->get($gqid);
                if ($g) $genreIds[] = $g->id;
            }
            if (count($genreIds) > 0) {
                $artist->genres()->syncWithoutDetaching($genreIds);
                $attached += count($genreIds);
            }

            // Links: artist_links (enum-aligned)
            foreach ($this->buildLinks($data) as $link) {
                ArtistLink::firstOrCreate(
                    [
                        'artist_id' => $artist->id,
                        'type'      => $link['type'],
                        'url'       => $link['url'],
                    ],
                    [
                        'source'      => 'wikidata',
                        'is_official' => $link['is_official'],
                    ]
                );
                $linksTouched++;
            }
        }

        Log::info('Wikidata artist enrich batch done', [
            'artistsUpserted' => $upserted,
            'genreLinksAddedOrConfirmed' => $attached,
            'artistLinksInsertedOrConfirmed' => $linksTouched,
            'artistsInResponse' => count($byArtist),
        ]);
    }

    /**
     * @return array<int, array{type:string,url:string,is_official:bool}>
     */
    private function buildLinks(array $data): array
    {
        $links = [];

        // WEBSITE (P856)
        if (!empty($data['officialWebsite'])) {
            $url = $this->normalizeUrl($data['officialWebsite']);
            if ($url) $links[] = ['type' => ArtistLinkType::WEBSITE->value, 'url' => $url, 'is_official' => true];
        }

        // TWITTER (P2002)
        if (!empty($data['twitter'])) {
            $links[] = ['type' => ArtistLinkType::TWITTER->value, 'url' => "https://twitter.com/{$data['twitter']}", 'is_official' => true];
        }

        // INSTAGRAM (P2003)
        if (!empty($data['instagram'])) {
            $links[] = ['type' => ArtistLinkType::INSTAGRAM->value, 'url' => "https://www.instagram.com/{$data['instagram']}/", 'is_official' => true];
        }

        // FACEBOOK (P2013)
        if (!empty($data['facebook'])) {
            $links[] = ['type' => ArtistLinkType::FACEBOOK->value, 'url' => "https://www.facebook.com/{$data['facebook']}", 'is_official' => true];
        }

        // YOUTUBE (P2397)
        if (!empty($data['youtubeChannel'])) {
            $links[] = ['type' => ArtistLinkType::YOUTUBE->value, 'url' => "https://www.youtube.com/channel/{$data['youtubeChannel']}", 'is_official' => true];
        }

        // SPOTIFY (P1902)
        if (!empty($data['spotifyArtistId'])) {
            $links[] = ['type' => ArtistLinkType::SPOTIFY->value, 'url' => "https://open.spotify.com/artist/{$data['spotifyArtistId']}", 'is_official' => true];
        }

        // APPLE_MUSIC (P2850) - not in fromWikidataProperty currently, but in enum list
        if (!empty($data['appleMusicArtistId'])) {
            $links[] = ['type' => ArtistLinkType::APPLE_MUSIC->value, 'url' => "https://music.apple.com/us/artist/{$data['appleMusicArtistId']}", 'is_official' => true];
        }

        // SOUNDCLOUD (P3040)
        if (!empty($data['soundcloudId'])) {
            $links[] = ['type' => ArtistLinkType::SOUNDCLOUD->value, 'url' => "https://soundcloud.com/{$data['soundcloudId']}", 'is_official' => true];
        }

        // BANDCAMP (P3283) - best-effort URL
        if (!empty($data['bandcampId'])) {
            $links[] = ['type' => ArtistLinkType::BANDCAMP->value, 'url' => "https://bandcamp.com/{$data['bandcampId']}", 'is_official' => true];
        }

        // REDDIT (P3984)
        if (!empty($data['subreddit'])) {
            $links[] = ['type' => ArtistLinkType::REDDIT->value, 'url' => "https://www.reddit.com/r/{$data['subreddit']}/", 'is_official' => false];
        }

        // Normalize + de-dupe
        $out = [];
        $seen = [];
        foreach ($links as $l) {
            $url = $this->normalizeUrl($l['url']);
            if (!$url) continue;

            $key = $l['type'] . '|' . $url;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $out[] = ['type' => $l['type'], 'url' => $url, 'is_official' => (bool) $l['is_official']];
        }

        return $out;
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (! $url) return null;
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) return null;
        return $url;
    }

    /**
     * @param array<int,string> $instanceOfQids
     */
    private function inferArtistType(array $instanceOfQids): ?string
    {
        $set = array_flip(array_unique($instanceOfQids));
        if (isset($set['Q5'])) return 'person';
        if (isset($set['Q215380'])) return 'group';
        return null;
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

    private function commonsFilename(?string $value): ?string
    {
        if (! $value) return null;
        $pos = strrpos($value, '/');
        $tail = $pos !== false ? substr($value, $pos + 1) : $value;
        return ($tail !== '') ? urldecode($tail) : null;
    }

    private function computeSortName(?string $displayName, ?string $givenName, ?string $familyName): ?string
    {
        $displayName = $displayName ? trim($displayName) : null;
        if (! $displayName) return null;

        $givenName = $givenName ? trim($givenName) : null;
        $familyName = $familyName ? trim($familyName) : null;

        if ($familyName) {
            return $givenName ? "{$familyName}, {$givenName}" : $familyName;
        }

        $n = mb_strtolower($displayName);
        if (str_starts_with($n, 'the ')) $n = trim(substr($n, 4));
        return $n;
    }
}
