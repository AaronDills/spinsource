<?php

namespace App\Jobs;

use App\Enums\ArtistLinkType;
use App\Jobs\Concerns\RetriesOnDeadlock;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use Illuminate\Support\Facades\Log;

/**
 * Enrich artist data from Wikidata using multiple SPARQL queries.
 * Updates core fields + links + country association + genres.
 */
class WikidataEnrichArtists extends WikidataJob
{
    use RetriesOnDeadlock;

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

        // Format QIDs as VALUES clause for SPARQL
        $values = implode(' ', array_map(fn ($qid) => "wd:{$qid}", $this->artistQids));

        // Fetch basic artist data
        $basicSparql = DataSourceQuery::get('artist_enrich_basic', 'wikidata', [
            'values' => $values,
        ]);

        $basicResponse = $this->executeWdqsRequest($basicSparql);
        if ($basicResponse === null) {
            return; // Rate limited
        }

        // Fetch genre relationships
        $genresSparql = DataSourceQuery::get('artist_enrich_genres', 'wikidata', [
            'values' => $values,
        ]);

        $genresResponse = $this->executeWdqsRequest($genresSparql);
        if ($genresResponse === null) {
            return; // Rate limited
        }

        // Fetch social/streaming links
        $linksSparql = DataSourceQuery::get('artist_enrich_links', 'wikidata', [
            'values' => $values,
        ]);

        $linksResponse = $this->executeWdqsRequest($linksSparql);
        if ($linksResponse === null) {
            return; // Rate limited
        }

        $basicResults = $basicResponse->json('results.bindings', []);
        $genresResults = $genresResponse->json('results.bindings', []);
        $linksResults = $linksResponse->json('results.bindings', []);

        if (empty($basicResults)) {
            $this->logEnd('Enrich artists (no results)', [
                'count' => count($this->artistQids),
            ]);

            return;
        }

        $countriesToUpsert = [];
        $genresToAttach = [];
        $artistUpdates = [];
        $artistLinks = [];

        // Process basic data
        foreach ($basicResults as $row) {
            $artistUri = $row['artist']['value'] ?? null;
            $artistQid = $this->qidFromEntityUrl($artistUri);

            if (! $artistQid) {
                continue;
            }

            $countryQid = $this->qidFromEntityUrl($row['country']['value'] ?? null);

            if ($countryQid) {
                $countriesToUpsert[$countryQid] = [
                    'wikidata_qid' => $countryQid,
                    'name' => $row['countryLabel']['value'] ?? $countryQid,
                ];
            }

            $isHuman = ($row['isHuman']['value'] ?? 'false') === 'true';

            $artistUpdates[$artistQid] = [
                'artistLabel' => $row['artistLabel']['value'] ?? null,
                'artistDescription' => $row['artistDescription']['value'] ?? null,
                'instanceOf' => $isHuman ? 'Q5' : null, // Q5 = human
                'countryQid' => $countryQid,
                'officialWebsite' => $row['officialWebsite']['value'] ?? null,
                'imageCommons' => $row['imageCommons']['value'] ?? null,
                'logoCommons' => $row['logoCommons']['value'] ?? null,
                'commonsCategory' => $row['commonsCategory']['value'] ?? null,
                'formed' => $row['formed']['value'] ?? null,
                'disbanded' => $row['disbanded']['value'] ?? null,
                'musicBrainzId' => $row['musicBrainzId']['value'] ?? null,
                'givenName' => $row['givenNameLabel']['value'] ?? null,
                'familyName' => $row['familyNameLabel']['value'] ?? null,
            ];
        }

        // Process genre relationships
        foreach ($genresResults as $row) {
            $artistQid = $this->qidFromEntityUrl($row['artist']['value'] ?? null);
            $genreQid = $this->qidFromEntityUrl($row['genre']['value'] ?? null);

            if ($artistQid && $genreQid) {
                $genresToAttach[$artistQid][] = $genreQid;
            }
        }

        // Process links/social data
        foreach ($linksResults as $row) {
            $artistQid = $this->qidFromEntityUrl($row['artist']['value'] ?? null);

            if (! $artistQid || ! isset($artistUpdates[$artistQid])) {
                continue;
            }

            // Add link data to artist updates
            $artistUpdates[$artistQid]['twitter'] = $row['twitter']['value'] ?? null;
            $artistUpdates[$artistQid]['instagram'] = $row['instagram']['value'] ?? null;
            $artistUpdates[$artistQid]['facebook'] = $row['facebook']['value'] ?? null;
            $artistUpdates[$artistQid]['youtubeChannel'] = $row['youtubeChannel']['value'] ?? null;
            $artistUpdates[$artistQid]['spotifyArtistId'] = $row['spotifyArtistId']['value'] ?? null;
            $artistUpdates[$artistQid]['appleMusicArtistId'] = $row['appleMusicArtistId']['value'] ?? null;

            // Build artist links array
            foreach ([
                ArtistLinkType::TWITTER->value => $row['twitter']['value'] ?? null,
                ArtistLinkType::INSTAGRAM->value => $row['instagram']['value'] ?? null,
                ArtistLinkType::FACEBOOK->value => $row['facebook']['value'] ?? null,
                ArtistLinkType::YOUTUBE->value => $row['youtubeChannel']['value'] ?? null,
                ArtistLinkType::WEBSITE->value => $artistUpdates[$artistQid]['officialWebsite'] ?? null,
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

        $errors = 0;

        // Use deadlock-aware transaction to handle transient MySQL deadlocks
        $this->runWithDeadlockRetry(function () use ($countriesToUpsert, $artistUpdates, $genresToAttach, $artistLinks, &$errors) {
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
            $now = now();

            foreach ($artistUpdates as $qid => $data) {
                try {
                    /** @var \App\Models\Artist|null $artist */
                    $artist = $artists->get($qid);
                    if (! $artist) {
                        continue;
                    }

                    $artist->description = $data['artistDescription'] ?? $artist->description;
                    $artist->instance_of = $data['instanceOf'] ?? $artist->instance_of;

                    $artist->official_website = $data['officialWebsite'] ?? $artist->official_website;

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

                    // Track provenance
                    $artist->source = 'wikidata';
                    $artist->source_last_synced_at = $now;

                    $artist->save();
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('WikidataEnrichArtists: Failed to update artist', [
                        'qid' => $qid,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with next artist - don't fail entire batch
                }
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

            // Links - use upsert for idempotency
            // Unique constraint is (artist_id, type, url), so we group links by artist
            // and upsert all at once to handle changes properly
            if (! empty($artistLinks)) {
                $linksToUpsert = [];
                $now = now();

                foreach ($artistLinks as $link) {
                    $artist = Artist::where('wikidata_qid', $link['artist_qid'])->first();
                    if (! $artist) {
                        continue;
                    }

                    $linksToUpsert[] = [
                        'artist_id' => $artist->id,
                        'type' => $link['type'],
                        'url' => $link['url'],
                        'source' => 'wikidata',
                        'is_official' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($linksToUpsert)) {
                    // Upsert on (artist_id, type, url) - if same combo exists, just update timestamps
                    // This is idempotent: running twice yields same state
                    ArtistLink::upsert(
                        $linksToUpsert,
                        ['artist_id', 'type', 'url'],
                        ['source', 'is_official', 'updated_at']
                    );
                }
            }
        });

        Log::info('Enriched artists', [
            'artists' => count($artistUpdates),
            'countries' => count($countriesToUpsert),
            'errors' => $errors,
        ]);

        $this->logEnd('Enrich artists', [
            'artists' => count($artistUpdates),
            'countries' => count($countriesToUpsert),
            'errors' => $errors,
        ]);
    }
}
