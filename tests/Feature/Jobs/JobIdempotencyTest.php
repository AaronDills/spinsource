<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MusicBrainzFetchTracklist;
use App\Jobs\WikidataEnrichAlbumCovers;
use App\Jobs\WikidataSeedAlbums;
use App\Jobs\WikidataSeedGenres;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Country;
use App\Models\DataSourceQuery;
use App\Models\Genre;
use App\Models\Track;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests to verify job idempotency - running the same job twice should not create duplicates.
 */
class JobIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test country for FK constraints
        Country::create([
            'wikidata_qid' => 'Q30',
            'name' => 'United States',
        ]);
    }

    /**
     * Test that WikidataSeedAlbums running twice doesn't create duplicates.
     */
    public function test_wikidata_seed_albums_is_idempotent(): void
    {
        Carbon::setTestNow('2024-01-02 00:00:00');

        // Seed the query
        DataSourceQuery::create([
            'name' => 'albums',
            'data_source' => 'wikidata',
            'query_type' => 'sparql',
            'query' => 'SELECT ?album ?artist ?albumLabel WHERE { VALUES ?artist { {{values}} } ?album wdt:P175 ?artist. } LIMIT 100',
            'is_active' => true,
        ]);

        // Create an artist with wikidata_qid so the job has something to fetch
        $artist = Artist::create([
            'name' => 'The Beatles',
            'wikidata_qid' => 'Q1299',
        ]);

        $bindingsFirst = [
            [
                'album' => ['value' => 'http://www.wikidata.org/entity/Q210593'],
                'artist' => ['value' => 'http://www.wikidata.org/entity/Q1299'],
                'albumLabel' => ['value' => 'Abbey Road'],
                'albumDescription' => ['value' => 'Studio album by The Beatles'],
                'publicationDate' => ['value' => '1969-09-26'],
            ],
        ];

        $bindingsSecond = [
            [
                'album' => ['value' => 'http://www.wikidata.org/entity/Q210593'],
                'artist' => ['value' => 'http://www.wikidata.org/entity/Q1299'],
                'albumLabel' => ['value' => 'Abbey Road (Remastered)'],
                'albumDescription' => ['value' => 'Remastered studio album by The Beatles'],
                'publicationDate' => ['value' => '1969-09-26'],
            ],
        ];

        Http::fake([
            '*' => Http::sequence()
                ->push(['results' => ['bindings' => $bindingsFirst]], 200)
                ->push(['results' => ['bindings' => $bindingsSecond]], 200),
        ]);

        $job = new WikidataSeedAlbums(null, 25, true);
        $job->handle();

        $this->assertDatabaseHas('albums', [
            'wikidata_qid' => 'Q210593',
            'title' => 'Abbey Road',
            'artist_id' => $artist->id,
        ]);

        $job2 = new WikidataSeedAlbums(null, 25, true);
        $job2->handle();

        $this->assertDatabaseCount('albums', 1);
        $this->assertDatabaseHas('albums', [
            'wikidata_qid' => 'Q210593',
            'title' => 'Abbey Road (Remastered)',
            'artist_id' => $artist->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * Test WikidataSeedGenres can be constructed with null afterOid.
     */
    public function test_wikidata_seed_genres_accepts_null_after_oid(): void
    {
        // This should not throw TypeError
        $job = new WikidataSeedGenres(null);
        $this->assertInstanceOf(WikidataSeedGenres::class, $job);

        // Also test with explicit 0
        $job2 = new WikidataSeedGenres(0);
        $this->assertInstanceOf(WikidataSeedGenres::class, $job2);
    }

    /**
     * Test that WikidataSeedGenres running twice doesn't create duplicates.
     */
    public function test_wikidata_seed_genres_is_idempotent(): void
    {
        Carbon::setTestNow('2024-01-02 00:00:00');

        // Seed the query
        DataSourceQuery::create([
            'name' => 'genres',
            'data_source' => 'wikidata',
            'query_type' => 'sparql',
            'query' => 'SELECT ?genre WHERE { ?genre wdt:P31 wd:Q188451. } {{after_filter}} LIMIT {{limit}}',
            'is_active' => true,
        ]);

        $bindingsFirst = [
            [
                'genre' => ['value' => 'http://www.wikidata.org/entity/Q11399'],
                'genreLabel' => ['value' => 'Rock music'],
                'country' => ['value' => 'http://www.wikidata.org/entity/Q145'],
                'countryLabel' => ['value' => 'United Kingdom'],
            ],
        ];

        $bindingsSecond = [
            [
                'genre' => ['value' => 'http://www.wikidata.org/entity/Q11399'],
                'genreLabel' => ['value' => 'Rock music (updated)'],
                'country' => ['value' => 'http://www.wikidata.org/entity/Q145'],
                'countryLabel' => ['value' => 'UK'],
            ],
        ];

        Http::fake([
            '*' => Http::sequence()
                ->push(['results' => ['bindings' => $bindingsFirst]], 200)
                ->push(['results' => ['bindings' => $bindingsSecond]], 200),
        ]);

        $job = new WikidataSeedGenres;
        $job->handle();

        $this->assertDatabaseHas('genres', [
            'wikidata_qid' => 'Q11399',
            'name' => 'Rock music',
        ]);
        $this->assertDatabaseHas('countries', [
            'wikidata_qid' => 'Q145',
            'name' => 'United Kingdom',
        ]);

        $job2 = new WikidataSeedGenres;
        $job2->handle();

        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseHas('genres', [
            'wikidata_qid' => 'Q11399',
            'name' => 'Rock music (updated)',
        ]);
        $this->assertDatabaseHas('countries', [
            'wikidata_qid' => 'Q145',
            'name' => 'UK',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Test that WikidataEnrichAlbumCovers only updates null covers.
     */
    public function test_wikidata_enrich_album_covers_respects_existing_covers(): void
    {
        Carbon::setTestNow('2024-01-01 00:00:00');

        // Seed the query
        DataSourceQuery::create([
            'name' => 'album_covers',
            'data_source' => 'wikidata',
            'query_type' => 'sparql',
            'query' => 'SELECT ?album ?coverImage WHERE { VALUES ?album { {{values}} } OPTIONAL { ?album wdt:P18 ?coverImage . } }',
            'is_active' => true,
        ]);

        $artist = Artist::create([
            'name' => 'Test Artist',
            'wikidata_qid' => 'Q12345',
        ]);

        $albumWithCover = Album::create([
            'title' => 'Album With Cover',
            'wikidata_qid' => 'Q11111',
            'artist_id' => $artist->id,
            'cover_image_commons' => 'existing_cover.jpg',
        ]);

        $albumWithoutCover = Album::create([
            'title' => 'Album Without Cover',
            'wikidata_qid' => 'Q22222',
            'artist_id' => $artist->id,
            'cover_image_commons' => null,
        ]);

        $bindings = [
            [
                'album' => ['value' => 'http://www.wikidata.org/entity/Q11111'],
                'coverImage' => ['value' => 'cover_from_wikidata.jpg'],
            ],
            [
                'album' => ['value' => 'http://www.wikidata.org/entity/Q22222'],
                'coverImage' => ['value' => 'new_cover.jpg'],
            ],
        ];

        Http::fake([
            '*' => Http::response(['results' => ['bindings' => $bindings]], 200),
        ]);

        $job = new WikidataEnrichAlbumCovers(['Q11111', 'Q22222']);
        $job->handle();

        $albumWithCover->refresh();
        $albumWithoutCover->refresh();

        $this->assertEquals('existing_cover.jpg', $albumWithCover->cover_image_commons);
        $this->assertEquals('new_cover.jpg', $albumWithoutCover->cover_image_commons);
        $this->assertEquals('wikidata', $albumWithoutCover->source);
        $this->assertEquals(
            Carbon::now()->toDateTimeString(),
            $albumWithoutCover->source_last_synced_at->toDateTimeString()
        );

        Carbon::setTestNow();
    }

    /**
     * Test that MusicBrainzFetchTracklist with same data twice doesn't create duplicate tracks.
     */
    public function test_musicbrainz_fetch_tracklist_is_idempotent(): void
    {
        // Create artist and album
        $artist = Artist::create([
            'name' => 'Test Artist',
            'wikidata_qid' => 'Q12345',
        ]);

        $album = Album::create([
            'title' => 'Test Album',
            'wikidata_qid' => 'Q99999',
            'artist_id' => $artist->id,
            'musicbrainz_release_group_mbid' => 'abc12345-1234-1234-1234-123456789012',
        ]);

        // Mock MusicBrainz API responses
        $releasesResponse = [
            'releases' => [
                [
                    'id' => 'release-12345',
                    'status' => 'Official',
                    'country' => 'US',
                    'media' => [['format' => 'CD', 'track-count' => 2]],
                    'barcode' => '123456789',
                    'date' => '2020-01-01',
                ],
            ],
        ];

        $tracklistResponse = [
            'media' => [
                [
                    'position' => 1,
                    'tracks' => [
                        [
                            'title' => 'Track 1',
                            'position' => 1,
                            'number' => '1',
                            'length' => 180000,
                            'recording' => [
                                'id' => 'rec-11111111-1111-1111-1111-111111111111',
                                'title' => 'Track 1',
                            ],
                        ],
                        [
                            'title' => 'Track 2',
                            'position' => 2,
                            'number' => '2',
                            'length' => 200000,
                            'recording' => [
                                'id' => 'rec-22222222-2222-2222-2222-222222222222',
                                'title' => 'Track 2',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*release-group*' => Http::response($releasesResponse, 200),
            '*release/release-12345*' => Http::response($tracklistResponse, 200),
        ]);

        // First run
        $job = new MusicBrainzFetchTracklist($album->id);
        $job->handle();

        $this->assertDatabaseCount('tracks', 2);
        $this->assertEquals(2, $album->tracks()->count());

        // Second run - should NOT create duplicates
        $job2 = new MusicBrainzFetchTracklist($album->id, true); // force reselect
        $job2->handle();

        $this->assertDatabaseCount('tracks', 2);
        $this->assertEquals(2, $album->tracks()->count());

        // Verify correct track data
        $track1 = Track::where('musicbrainz_recording_id', 'rec-11111111-1111-1111-1111-111111111111')->first();
        $this->assertNotNull($track1);
        $this->assertEquals('Track 1', $track1->title);
        $this->assertEquals(1, $track1->position);
    }

    /**
     * Test that album upsert updates existing record rather than creating duplicate.
     */
    public function test_album_upsert_updates_existing_record(): void
    {
        $artist = Artist::create([
            'name' => 'Test Artist',
            'wikidata_qid' => 'Q12345',
        ]);

        // Create initial album
        $album = Album::create([
            'title' => 'Original Title',
            'wikidata_qid' => 'Q99999',
            'artist_id' => $artist->id,
            'release_year' => 2020,
        ]);

        $this->assertDatabaseCount('albums', 1);

        // Upsert with updated data
        Album::upsert(
            [
                [
                    'wikidata_qid' => 'Q99999',
                    'title' => 'Updated Title',
                    'artist_id' => $artist->id,
                    'release_year' => 2021,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['wikidata_qid'],
            ['title', 'release_year', 'updated_at']
        );

        $this->assertDatabaseCount('albums', 1);
        $album->refresh();
        $this->assertEquals('Updated Title', $album->title);
        $this->assertEquals(2021, $album->release_year);
    }

    /**
     * Test that genre upsert is idempotent.
     */
    public function test_genre_update_or_create_is_idempotent(): void
    {
        // First creation
        Genre::updateOrCreate(
            ['wikidata_qid' => 'Q11399'],
            ['name' => 'Rock music']
        );

        $this->assertDatabaseCount('genres', 1);

        // Second call - should update, not create
        Genre::updateOrCreate(
            ['wikidata_qid' => 'Q11399'],
            ['name' => 'Rock Music (Updated)']
        );

        $this->assertDatabaseCount('genres', 1);
        $this->assertEquals('Rock Music (Updated)', Genre::first()->name);
    }

    /**
     * Test that track upsert with recording ID is idempotent.
     */
    public function test_track_upsert_by_recording_id_is_idempotent(): void
    {
        $artist = Artist::create([
            'name' => 'Test Artist',
            'wikidata_qid' => 'Q12345',
        ]);

        $album = Album::create([
            'title' => 'Test Album',
            'wikidata_qid' => 'Q99999',
            'artist_id' => $artist->id,
        ]);

        $trackData = [
            'album_id' => $album->id,
            'musicbrainz_recording_id' => 'rec-11111111-1111-1111-1111-111111111111',
            'musicbrainz_release_id' => 'release-12345',
            'title' => 'Track 1',
            'position' => 1,
            'disc_number' => 1,
            'length_ms' => 180000,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // First upsert
        Track::upsert(
            [$trackData],
            ['album_id', 'musicbrainz_recording_id'],
            ['title', 'position', 'length_ms', 'updated_at']
        );

        $this->assertDatabaseCount('tracks', 1);

        // Second upsert with updated title
        $trackData['title'] = 'Track 1 (Remastered)';
        Track::upsert(
            [$trackData],
            ['album_id', 'musicbrainz_recording_id'],
            ['title', 'position', 'length_ms', 'updated_at']
        );

        $this->assertDatabaseCount('tracks', 1);
        $this->assertEquals('Track 1 (Remastered)', Track::first()->title);
    }

    /**
     * Test that artist_genre pivot uses syncWithoutDetaching correctly.
     */
    public function test_artist_genre_sync_without_detaching_is_idempotent(): void
    {
        $artist = Artist::create([
            'name' => 'Test Artist',
            'wikidata_qid' => 'Q12345',
        ]);

        $genre1 = Genre::create([
            'name' => 'Rock',
            'wikidata_qid' => 'Q11399',
        ]);

        $genre2 = Genre::create([
            'name' => 'Pop',
            'wikidata_qid' => 'Q11401',
        ]);

        // First sync
        $artist->genres()->syncWithoutDetaching([$genre1->id]);
        $this->assertEquals(1, $artist->genres()->count());

        // Second sync with same genre - should not duplicate
        $artist->genres()->syncWithoutDetaching([$genre1->id]);
        $this->assertEquals(1, $artist->genres()->count());

        // Third sync with additional genre
        $artist->genres()->syncWithoutDetaching([$genre1->id, $genre2->id]);
        $this->assertEquals(2, $artist->genres()->count());

        // Fourth sync with same genres - should not duplicate
        $artist->genres()->syncWithoutDetaching([$genre1->id, $genre2->id]);
        $this->assertEquals(2, $artist->genres()->count());
    }

    /**
     * Test ingestion checkpoint bumping is monotonic.
     */
    public function test_ingestion_checkpoint_bump_is_monotonic(): void
    {
        $checkpoint = \App\Models\IngestionCheckpoint::forKey('test');

        // Initial bump
        $checkpoint->bumpSeenOid(100);
        $this->assertEquals(100, $checkpoint->last_seen_oid);

        // Higher value - should update
        $checkpoint->bumpSeenOid(200);
        $this->assertEquals(200, $checkpoint->last_seen_oid);

        // Lower value - should NOT update (monotonic)
        $checkpoint->bumpSeenOid(150);
        $this->assertEquals(200, $checkpoint->last_seen_oid);

        // Same value - should NOT update
        $checkpoint->bumpSeenOid(200);
        $this->assertEquals(200, $checkpoint->last_seen_oid);
    }

    /**
     * Test that country upsert is idempotent.
     */
    public function test_country_upsert_is_idempotent(): void
    {
        // First upsert
        Country::upsert(
            [['wikidata_qid' => 'Q145', 'name' => 'United Kingdom']],
            ['wikidata_qid'],
            ['name']
        );

        $this->assertDatabaseCount('countries', 2); // Including the one from setUp

        // Second upsert - should update, not create
        Country::upsert(
            [['wikidata_qid' => 'Q145', 'name' => 'UK']],
            ['wikidata_qid'],
            ['name']
        );

        $this->assertDatabaseCount('countries', 2);
        $this->assertEquals('UK', Country::where('wikidata_qid', 'Q145')->first()->name);
    }
}
