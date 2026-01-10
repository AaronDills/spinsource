<?php

namespace Tests\Unit\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Track;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function truncate_description_returns_null_for_null_input()
    {
        $result = SeoService::truncateDescription(null);

        $this->assertNull($result);
    }

    /** @test */
    public function truncate_description_returns_null_for_empty_string()
    {
        $result = SeoService::truncateDescription('');

        $this->assertNull($result);
    }

    /** @test */
    public function truncate_description_returns_text_unchanged_when_under_limit()
    {
        $text = 'This is a short description.';
        $result = SeoService::truncateDescription($text);

        $this->assertEquals($text, $result);
    }

    /** @test */
    public function truncate_description_truncates_at_exact_boundary()
    {
        // Exactly 160 characters
        $text = str_repeat('a', 160);
        $result = SeoService::truncateDescription($text);

        $this->assertEquals($text, $result);
    }

    /** @test */
    public function truncate_description_truncates_when_over_limit()
    {
        $text = str_repeat('a', 200);
        $result = SeoService::truncateDescription($text);

        // Should be 157 chars + '...' = 160
        $this->assertEquals(160, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    /** @test */
    public function truncate_description_strips_html_tags()
    {
        $text = '<p>This is <strong>bold</strong> text.</p>';
        $result = SeoService::truncateDescription($text);

        $this->assertEquals('This is bold text.', $result);
    }

    /** @test */
    public function truncate_description_normalizes_whitespace()
    {
        $text = "This  has   multiple\n\nspaces\tand\ttabs.";
        $result = SeoService::truncateDescription($text);

        $this->assertEquals('This has multiple spaces and tabs.', $result);
    }

    /** @test */
    public function truncate_description_respects_custom_max_length()
    {
        $text = str_repeat('a', 100);
        $result = SeoService::truncateDescription($text, 50);

        $this->assertEquals(50, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    /** @test */
    public function truncate_description_handles_unicode_correctly()
    {
        $text = 'Björk is an Icelandic singer. 日本語のテキスト。';
        $result = SeoService::truncateDescription($text);

        // Should preserve unicode characters
        $this->assertStringContainsString('Björk', $result);
        $this->assertStringContainsString('日本語', $result);
    }

    /** @test */
    public function canonical_url_with_path_builds_correct_url()
    {
        config(['app.url' => 'https://example.com']);

        $result = SeoService::canonicalUrl('/artists/test');

        $this->assertEquals('https://example.com/artists/test', $result);
    }

    /** @test */
    public function canonical_url_with_path_handles_trailing_slash_in_app_url()
    {
        config(['app.url' => 'https://example.com/']);

        $result = SeoService::canonicalUrl('/artists/test');

        $this->assertEquals('https://example.com/artists/test', $result);
    }

    /** @test */
    public function canonical_url_with_path_handles_missing_leading_slash()
    {
        config(['app.url' => 'https://example.com']);

        $result = SeoService::canonicalUrl('artists/test');

        $this->assertEquals('https://example.com/artists/test', $result);
    }

    /** @test */
    public function canonical_url_without_path_removes_query_parameters()
    {
        // This requires a request context, so we'll make a GET request
        $response = $this->get('/search?q=test&page=2');

        $canonical = SeoService::canonicalUrl();

        $this->assertStringEndsNotWith('?q=test&page=2', $canonical);
        $this->assertStringContainsString('/search', $canonical);
    }

    /** @test */
    public function default_og_image_returns_asset_url()
    {
        $result = SeoService::defaultOgImage();

        $this->assertStringContainsString('/images/og-default.svg', $result);
    }

    /** @test */
    public function artist_json_ld_includes_required_fields()
    {
        $artist = Artist::factory()->create([
            'name' => 'The Beatles',
        ]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertEquals('https://schema.org', $result['@context']);
        $this->assertEquals('MusicGroup', $result['@type']);
        $this->assertEquals('The Beatles', $result['name']);
        $this->assertStringContainsString('/artist/', $result['url']);
        $this->assertStringContainsString((string) $artist->id, $result['url']);
    }

    /** @test */
    public function artist_json_ld_includes_image_when_present()
    {
        $artist = Artist::factory()->create([
            'image_commons' => 'The_Beatles_in_1964.jpg',
        ]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('image', $result);
        $this->assertStringContainsString('commons.wikimedia.org', $result['image']);
        $this->assertStringContainsString('The_Beatles_in_1964.jpg', $result['image']);
        $this->assertStringContainsString('width=600', $result['image']);
    }

    /** @test */
    public function artist_json_ld_includes_truncated_description()
    {
        $artist = Artist::factory()->create([
            'description' => str_repeat('Long description. ', 50), // Over 300 chars
        ]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('description', $result);
        $this->assertLessThanOrEqual(300, mb_strlen($result['description']));
        $this->assertStringEndsWith('...', $result['description']);
    }

    /** @test */
    public function artist_json_ld_includes_genres_when_loaded()
    {
        $artist = Artist::factory()->create();
        $genres = Genre::factory()->count(3)->create();
        $artist->genres()->attach($genres);
        $artist->load('genres');

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('genre', $result);
        $this->assertIsArray($result['genre']);
        $this->assertCount(3, $result['genre']);
    }

    /** @test */
    public function artist_json_ld_omits_genres_when_not_loaded()
    {
        $artist = Artist::factory()->create();

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayNotHasKey('genre', $result);
    }

    /** @test */
    public function artist_json_ld_includes_all_same_as_links()
    {
        $artist = Artist::factory()->create([
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/The_Beatles',
            'official_website' => 'https://www.thebeatles.com',
            'spotify_artist_id' => '3WrFJ7ztbogyGnTHbHJFl2',
            'apple_music_artist_id' => '136975',
            'musicbrainz_artist_mbid' => 'b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d',
            'discogs_artist_id' => '82730',
            'wikidata_qid' => 'Q1299',
        ]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('sameAs', $result);
        $this->assertIsArray($result['sameAs']);
        $this->assertContains('https://en.wikipedia.org/wiki/The_Beatles', $result['sameAs']);
        $this->assertContains('https://www.thebeatles.com', $result['sameAs']);
        $this->assertContains('https://open.spotify.com/artist/3WrFJ7ztbogyGnTHbHJFl2', $result['sameAs']);
        $this->assertContains('https://music.apple.com/artist/136975', $result['sameAs']);
        $this->assertContains('https://musicbrainz.org/artist/b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d', $result['sameAs']);
        $this->assertContains('https://www.discogs.com/artist/82730', $result['sameAs']);
        $this->assertContains('https://www.wikidata.org/wiki/Q1299', $result['sameAs']);
    }

    /** @test */
    public function artist_json_ld_includes_artist_links_when_loaded()
    {
        $artist = Artist::factory()->create();
        ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'url' => 'https://example.com/custom-link',
        ]);
        $artist->load('links');

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('sameAs', $result);
        $this->assertContains('https://example.com/custom-link', $result['sameAs']);
    }

    /** @test */
    public function artist_json_ld_deduplicates_same_as_links()
    {
        $artist = Artist::factory()->create([
            'wikipedia_url' => 'https://example.com/duplicate',
        ]);
        ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'url' => 'https://example.com/duplicate', // Same as wikipedia_url
        ]);
        $artist->load('links');

        $result = SeoService::artistJsonLd($artist);

        // Should only appear once
        $matches = array_filter($result['sameAs'], fn ($url) => $url === 'https://example.com/duplicate');
        $this->assertCount(1, $matches);
    }

    /** @test */
    public function artist_json_ld_includes_country_when_loaded()
    {
        $country = Country::factory()->create(['name' => 'United Kingdom']);
        $artist = Artist::factory()->create(['country_id' => $country->id]);
        $artist->load('country');

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('foundingLocation', $result);
        $this->assertEquals('Country', $result['foundingLocation']['@type']);
        $this->assertEquals('United Kingdom', $result['foundingLocation']['name']);
    }

    /** @test */
    public function artist_json_ld_omits_country_when_not_loaded()
    {
        $artist = Artist::factory()->create();

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayNotHasKey('foundingLocation', $result);
    }

    /** @test */
    public function artist_json_ld_includes_founding_date()
    {
        $artist = Artist::factory()->create(['formed_year' => 1960]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('foundingDate', $result);
        $this->assertEquals('1960', $result['foundingDate']);
    }

    /** @test */
    public function artist_json_ld_includes_dissolution_date()
    {
        $artist = Artist::factory()->create([
            'formed_year' => 1960,
            'disbanded_year' => 1970,
        ]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayHasKey('dissolutionDate', $result);
        $this->assertEquals('1970', $result['dissolutionDate']);
    }

    /** @test */
    public function artist_json_ld_omits_optional_fields_when_null()
    {
        $artist = Artist::factory()->create([
            'image_commons' => null,
            'description' => null,
            'wikipedia_url' => null,
            'formed_year' => null,
            'disbanded_year' => null,
        ]);

        $result = SeoService::artistJsonLd($artist);

        $this->assertArrayNotHasKey('image', $result);
        $this->assertArrayNotHasKey('description', $result);
        $this->assertArrayNotHasKey('foundingDate', $result);
        $this->assertArrayNotHasKey('dissolutionDate', $result);
    }

    /** @test */
    public function album_json_ld_includes_required_fields()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'title' => 'Abbey Road',
            'artist_id' => $artist->id,
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertEquals('https://schema.org', $result['@context']);
        $this->assertEquals('MusicAlbum', $result['@type']);
        $this->assertEquals('Abbey Road', $result['name']);
        $this->assertStringContainsString('/album/', $result['url']);
        $this->assertStringContainsString((string) $album->id, $result['url']);
    }

    /** @test */
    public function album_json_ld_includes_cover_image_when_present()
    {
        $album = Album::factory()->create([
            'cover_image_commons' => 'Abbey_Road.jpg',
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('image', $result);
        $this->assertStringContainsString('Abbey_Road.jpg', $result['image']);
    }

    /** @test */
    public function album_json_ld_includes_truncated_description()
    {
        $album = Album::factory()->create([
            'description' => str_repeat('Long description. ', 50),
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('description', $result);
        $this->assertLessThanOrEqual(300, mb_strlen($result['description']));
    }

    /** @test */
    public function album_json_ld_includes_artist_when_loaded()
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $album->load('artist');

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('byArtist', $result);
        $this->assertEquals('MusicGroup', $result['byArtist']['@type']);
        $this->assertEquals('The Beatles', $result['byArtist']['name']);
        $this->assertStringContainsString('/artist/', $result['byArtist']['url']);
        $this->assertStringContainsString((string) $artist->id, $result['byArtist']['url']);
    }

    /** @test */
    public function album_json_ld_omits_artist_when_not_loaded()
    {
        $album = Album::factory()->create();

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayNotHasKey('byArtist', $result);
    }

    /** @test */
    public function album_json_ld_includes_release_date_when_present()
    {
        $album = Album::factory()->create([
            'release_date' => '1969-09-26',
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('datePublished', $result);
        $this->assertEquals('1969-09-26', $result['datePublished']);
    }

    /** @test */
    public function album_json_ld_falls_back_to_release_year()
    {
        $album = Album::factory()->create([
            'release_date' => null,
            'release_year' => 1969,
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('datePublished', $result);
        $this->assertEquals('1969', $result['datePublished']);
    }

    /** @test */
    public function album_json_ld_prefers_release_date_over_year()
    {
        $album = Album::factory()->create([
            'release_date' => '1969-09-26',
            'release_year' => 1969,
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertEquals('1969-09-26', $result['datePublished']);
    }

    /** @test */
    public function album_json_ld_maps_album_types_correctly()
    {
        $typeTests = [
            'album' => 'https://schema.org/AlbumRelease',
            'ep' => 'https://schema.org/EPRelease',
            'single' => 'https://schema.org/SingleRelease',
            'compilation' => 'https://schema.org/CompilationAlbum',
            'live' => 'https://schema.org/LiveAlbum',
            'soundtrack' => 'https://schema.org/SoundtrackAlbum',
        ];

        foreach ($typeTests as $albumType => $expectedSchemaType) {
            $album = Album::factory()->create(['album_type' => $albumType]);
            $result = SeoService::albumJsonLd($album);

            $this->assertArrayHasKey('albumReleaseType', $result);
            $this->assertEquals($expectedSchemaType, $result['albumReleaseType']);
        }
    }

    /** @test */
    public function album_json_ld_omits_album_type_for_unknown_types()
    {
        $album = Album::factory()->create(['album_type' => 'remix']); // Not in the mapping

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayNotHasKey('albumReleaseType', $result);
    }

    /** @test */
    public function album_json_ld_includes_tracks_when_loaded()
    {
        $album = Album::factory()->create();
        Track::factory()->create([
            'album_id' => $album->id,
            'title' => 'Come Together',
            'position' => 1,
            'length_ms' => 259000, // 4:19
        ]);
        Track::factory()->create([
            'album_id' => $album->id,
            'title' => 'Something',
            'position' => 2,
            'length_ms' => 183000, // 3:03
        ]);
        $album->load('tracks');

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('numTracks', $result);
        $this->assertEquals(2, $result['numTracks']);

        $this->assertArrayHasKey('track', $result);
        $this->assertEquals('ItemList', $result['track']['@type']);
        $this->assertEquals(2, $result['track']['numberOfItems']);

        $tracks = $result['track']['itemListElement'];
        $this->assertCount(2, $tracks);

        $this->assertEquals('MusicRecording', $tracks[0]['@type']);
        $this->assertEquals('Come Together', $tracks[0]['name']);
        $this->assertEquals(1, $tracks[0]['position']);
        $this->assertEquals('PT4M19S', $tracks[0]['duration']); // ISO 8601
    }

    /** @test */
    public function album_json_ld_formats_track_duration_correctly()
    {
        $durationTests = [
            30000 => 'PT0M30S',     // 30 seconds
            60000 => 'PT1M0S',      // 1 minute
            65000 => 'PT1M5S',      // 1:05
            600000 => 'PT10M0S',    // 10 minutes
            3661000 => 'PT61M1S',   // 61:01 (over 1 hour, but formatted as minutes)
        ];

        foreach ($durationTests as $lengthMs => $expectedDuration) {
            $album = Album::factory()->create();
            Track::factory()->create([
                'album_id' => $album->id,
                'length_ms' => $lengthMs,
            ]);
            $album->load('tracks');

            $result = SeoService::albumJsonLd($album);
            $tracks = $result['track']['itemListElement'];

            $this->assertEquals($expectedDuration, $tracks[0]['duration']);
        }
    }

    /** @test */
    public function album_json_ld_omits_duration_when_null()
    {
        $album = Album::factory()->create();
        Track::factory()->create([
            'album_id' => $album->id,
            'length_ms' => null,
        ]);
        $album->load('tracks');

        $result = SeoService::albumJsonLd($album);
        $tracks = $result['track']['itemListElement'];

        $this->assertArrayNotHasKey('duration', $tracks[0]);
    }

    /** @test */
    public function album_json_ld_includes_all_same_as_links()
    {
        $album = Album::factory()->create([
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Abbey_Road',
            'spotify_album_id' => '0ETFjACtuP2ADo6LFhL6HN',
            'apple_music_album_id' => '1441164426',
            'musicbrainz_release_group_mbid' => '9e3a2e88-0b0f-46c8-b33d-956a761a6bb7',
            'wikidata_qid' => 'Q189045',
        ]);

        $result = SeoService::albumJsonLd($album);

        $this->assertArrayHasKey('sameAs', $result);
        $this->assertContains('https://en.wikipedia.org/wiki/Abbey_Road', $result['sameAs']);
        $this->assertContains('https://open.spotify.com/album/0ETFjACtuP2ADo6LFhL6HN', $result['sameAs']);
        $this->assertContains('https://music.apple.com/album/1441164426', $result['sameAs']);
        $this->assertContains('https://musicbrainz.org/release-group/9e3a2e88-0b0f-46c8-b33d-956a761a6bb7', $result['sameAs']);
        $this->assertContains('https://www.wikidata.org/wiki/Q189045', $result['sameAs']);
    }

    /** @test */
    public function website_json_ld_includes_required_fields()
    {
        config(['app.name' => 'Spinsearch']);
        config(['app.url' => 'https://spinsearch.example.com']);

        $result = SeoService::websiteJsonLd();

        $this->assertEquals('https://schema.org', $result['@context']);
        $this->assertEquals('WebSite', $result['@type']);
        $this->assertEquals('Spinsearch', $result['name']);
        $this->assertEquals('https://spinsearch.example.com', $result['url']);
        $this->assertArrayHasKey('description', $result);
    }

    /** @test */
    public function website_json_ld_includes_search_action()
    {
        $result = SeoService::websiteJsonLd();

        $this->assertArrayHasKey('potentialAction', $result);
        $this->assertEquals('SearchAction', $result['potentialAction']['@type']);
        $this->assertArrayHasKey('target', $result['potentialAction']);
        $this->assertEquals('EntryPoint', $result['potentialAction']['target']['@type']);
        $this->assertStringContainsString('{search_term_string}', $result['potentialAction']['target']['urlTemplate']);
        $this->assertEquals('required name=search_term_string', $result['potentialAction']['query-input']);
    }

    /** @test */
    public function encode_json_ld_produces_valid_json()
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicGroup',
            'name' => 'Test Artist',
        ];

        $result = SeoService::encodeJsonLd($data);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals($data, $decoded);
    }

    /** @test */
    public function encode_json_ld_does_not_escape_slashes()
    {
        $data = [
            'url' => 'https://example.com/path',
        ];

        $result = SeoService::encodeJsonLd($data);

        // Should NOT contain escaped slashes
        $this->assertStringNotContainsString('\/', $result);
        $this->assertStringContainsString('https://example.com/path', $result);
    }

    /** @test */
    public function encode_json_ld_preserves_unicode()
    {
        $data = [
            'name' => 'Björk',
            'description' => '日本語のテキスト',
        ];

        $result = SeoService::encodeJsonLd($data);

        // Should NOT escape unicode
        $this->assertStringContainsString('Björk', $result);
        $this->assertStringContainsString('日本語', $result);
        $this->assertStringNotContainsString('\u', $result);
    }

    /** @test */
    public function encode_json_ld_uses_pretty_print()
    {
        $data = [
            '@context' => 'https://schema.org',
            'name' => 'Test',
        ];

        $result = SeoService::encodeJsonLd($data);

        // Pretty print should include newlines and indentation
        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString('    ', $result); // Indentation
    }
}
