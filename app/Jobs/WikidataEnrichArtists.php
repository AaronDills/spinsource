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
 * Enrich artist data from Wikidata using an "enrich artist" sparql query.
 * Updates core fields + links + country association + genres.
 */
class WikidataEnrichArtists extends WikidataJob
{
    /** @param array<int,string> $artistQids */
    public function __construct(public array $artistQids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        if (empty($this->artistQids)) {
            return;
        }

        $this->withHeartbeat(function () {
            $this->doHandle();
        }, ['qids' => count($this->artistQids)]);
    }

    protected function doHandle(): void
    {
        $this->logStart('Enrich artists', [
            'count' => count($this->artistQids),
        ]);

        $sparql = $this->sparqlLoader->load('artists/enrich_artist');

        $response = $this->wikidata->querySparql($sparql, [
            'artistQids' => $this->artistQids,
        ]);

        DataSourceQuery::create([
            'source' => 'wikidata',
            'query_type' => 'sparql',
            'query_name' => 'artists/enrich_artist',
            'query' => $sparql,
            'response_meta' => [
                'qids' => $this->artistQids,
            ],
        ]);

        $results = $response['results']['bindings'] ?? [];
        if (empty($results)) {
            $this->logEnd('Enrich artists (no results)', [
                'count' => count($this->artistQids),
            ]);

            return;
        }

        $countriesToUpsert = [];
        $genresToAttach = [];
        $artistUpdates = [];
        $artistLinks = [];

        foreach ($results as $row) {
            $artistUri = $row['artist']['value'] ?? null;
            $artistQid = $this->wikidata->extractQid($artistUri);

            if (! $artistQid) {
                continue;
            }

            $countryQid = $this->wikidata->extractQid($row['country']['value'] ?? null);

            if ($countryQid) {
                $countriesToUpsert[$countryQid] = [
                    'wikidata_qid' => $countryQid,
                    'name' => $row['countryLabel']['value'] ?? $countryQid,
                ];
            }

            $genreQid = $this->wikidata->extractQid($row['genre']['value'] ?? null);
            if ($genreQid) {
                $genresToAttach[$artistQid][] = $genreQid;
            }

            $artistUpdates[$artistQid] = [
                'artistLabel' => $row['artistLabel']['value'] ?? null,
                'artistDescription' => $row['artistDescription']['value'] ?? null,
                'instanceOf' => $this->wikidata->extractQid($row['instanceOf']['value'] ?? null),
                'countryQid' => $countryQid,
                'officialWebsite' => $row['officialWebsite']['value'] ?? null,
                'wikipediaUrl' => $row['wikipediaUrl']['value'] ?? null,
                'imageCommons' => $row['imageCommons']['value'] ?? null,
                'logoCommons' => $row['logoCommons']['value'] ?? null,
                'commonsCategory' => $row['commonsCategory']['value'] ?? null,
                'formed' => $row['formed']['value'] ?? null,
                'disbanded' => $row['disbanded']['value'] ?? null,
                'musicBrainzId' => $row['musicBrainzId']['value'] ?? null,
                'givenName' => $row['givenName']['value'] ?? null,
                'familyName' => $row['familyName']['value'] ?? null,
                'twitter' => $row['twitter']['value'] ?? null,
                'instagram' => $row['instagram']['value'] ?? null,
                'facebook' => $row['facebook']['value'] ?? null,
                'youtubeChannel' => $row['youtubeChannel']['value'] ?? null,
                'spotifyArtistId' => $row['spotifyArtistId']['value'] ?? null,
                'appleMusicArtistId' => $row['appleMusicArtistId']['value'] ?? null,
            ];

            foreach ([
                ArtistLinkType::Twitter->value => $row['twitter']['value'] ?? null,
                ArtistLinkType::Instagram->value => $row['instagram']['value'] ?? null,
                ArtistLinkType::Facebook->value => $row['facebook']['value'] ?? null,
                ArtistLinkType::YouTube->value => $row['youtubeChannel']['value'] ?? null,
                ArtistLinkType::OfficialWebsite->value => $row['officialWebsite']['value'] ?? null,
                ArtistLinkType::Wikipedia->value => $row['wikipediaUrl']['value'] ?? null,
            ] as $type => $url) {
                if ($url) {
                    $artistLinks[] = [
                        'artist_qid' => $artistQid,
                        'type' => $type,
                        'url' => $url,
                    ];
                }
            }
        }

        DB::transaction(function () use ($countriesToUpsert, $artistUpdates, $genresToAttach, $artistLinks) {
            // Countries
            if (! empty($countriesToUpsert)) {
                Country::upsert(array_values($countriesToUpsert), ['wikidata_qid'], ['name']);
            }

            // Map country QIDs to IDs
            $countryIdByQid = collect();
            if (! empty($countriesToUpsert)) {
                $countryIdByQid = Country::query()
                    ->whereIn('wikidata_qid', array_keys($countriesToUpsert))
                    ->get(['id', 'wikidata_qid'])
                    ->keyBy('wikidata_qid')
                    ->map(fn ($c) => $c->id);
            }

            // Artists
            $artists = Artist::whereIn('wikidata_qid', array_keys($artistUpdates))->get()->keyBy('wikidata_qid');

            foreach ($artistUpdates as $qid => $data) {
                /** @var \App\Models\Artist|null $artist */
                $artist = $artists->get($qid);
                if (! $artist) {
                    continue;
                }

                $artist->description = $data['artistDescription'] ?? $artist->description;
                $artist->instance_of = $data['instanceOf'] ?? $artist->instance_of;

                $artist->official_website = $data['officialWebsite'] ?? $artist->official_website;
                $artist->wikipedia_url = $data['wikipediaUrl'] ?? $artist->wikipedia_url;

                $artist->image_commons = $data['imageCommons'] ?? $artist->image_commons;
                $artist->logo_commons = $data['logoCommons'] ?? $artist->logo_commons;
                $artist->commons_category = $data['commonsCategory'] ?? $artist->commons_category;

                $artist->formed = $data['formed'] ?? $artist->formed;
                $artist->disbanded = $data['disbanded'] ?? $artist->disbanded;

                $artist->musicbrainz_id = $data['musicBrainzId'] ?? $artist->musicbrainz_id;

                $artist->given_name = $data['givenName'] ?? $artist->given_name;
                $artist->family_name = $data['familyName'] ?? $artist->family_name;

                $artist->twitter = $data['twitter'] ?? $artist->twitter;
                $artist->instagram = $data['instagram'] ?? $artist->instagram;
                $artist->facebook = $data['facebook'] ?? $artist->facebook;
                $artist->youtube_channel = $data['youtubeChannel'] ?? $artist->youtube_channel;

                $artist->spotify_artist_id = $data['spotifyArtistId'] ?? $artist->spotify_artist_id;
                $artist->apple_music_artist_id = $data['appleMusicArtistId'] ?? $artist->apple_music_artist_id;

                $countryQid = $data['countryQid'] ?? null;
                if ($countryQid && $countryIdByQid->has($countryQid)) {
                    $artist->country_id = $countryIdByQid->get($countryQid);
                }

                $artist->save();
            }

            // Genres attach
            if (! empty($genresToAttach)) {
                $genreRows = Genre::whereIn('wikidata_qid', array_unique(array_merge(...array_values($genresToAttach))))
                    ->get(['id', 'wikidata_qid'])
                    ->keyBy('wikidata_qid');

                foreach ($genresToAttach as $artistQid => $genreQids) {
                    $artist = Artist::where('wikidata_qid', $artistQid)->first();
                    if (! $artist) {
                        continue;
                    }

                    $genreIds = collect($genreQids)
                        ->unique()
                        ->map(fn ($qid) => $genreRows->get($qid)?->id)
                        ->filter()
                        ->values()
                        ->toArray();

                    if (! empty($genreIds)) {
                        $artist->genres()->syncWithoutDetaching($genreIds);
                    }
                }
            }

            // Links
            if (! empty($artistLinks)) {
                foreach ($artistLinks as $link) {
                    $artist = Artist::where('wikidata_qid', $link['artist_qid'])->first();
                    if (! $artist) {
                        continue;
                    }

                    ArtistLink::updateOrCreate(
                        ['artist_id' => $artist->id, 'type' => $link['type']],
                        ['url' => $link['url']]
                    );
                }
            }
        });

        Log::info('Enriched artists', [
            'artists' => count($artistUpdates),
            'countries' => count($countriesToUpsert),
        ]);

        $this->logEnd('Enrich artists', [
            'artists' => count($artistUpdates),
            'countries' => count($countriesToUpsert),
        ]);
    }
}
