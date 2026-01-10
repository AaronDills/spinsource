<?php

namespace Tests\Unit\Controllers;

use App\Enums\AlbumType;
use App\Http\Controllers\ArtistController;
use App\Models\Album;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistControllerTest extends TestCase
{
    use RefreshDatabase;

    private ArtistController $controller;

    private \ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ArtistController();
        $this->reflection = new \ReflectionClass($this->controller);
    }

    /** @test */
    public function group_albums_by_type_groups_correctly()
    {
        $artist = Artist::factory()->create();

        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'album']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'album']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'ep']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'single']);

        $albums = $artist->albums;

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        $this->assertCount(3, $result); // 3 types: album, ep, single
        $this->assertEquals('Albums', $result[0]['label']);
        $this->assertEquals('EPs', $result[1]['label']);
        $this->assertEquals('Singles', $result[2]['label']);
        $this->assertCount(2, $result[0]['albums']); // 2 albums
        $this->assertCount(1, $result[1]['albums']); // 1 EP
        $this->assertCount(1, $result[2]['albums']); // 1 single
    }

    /** @test */
    public function group_albums_by_type_sorts_by_release_date_desc()
    {
        $artist = Artist::factory()->create();

        $album1 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => '2020-01-01',
        ]);

        $album2 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => '2023-06-15',
        ]);

        $album3 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => '2021-12-31',
        ]);

        $albums = $artist->albums;

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        // Should be sorted: 2023, 2021, 2020
        $this->assertEquals($album2->id, $result[0]['albums'][0]->id);
        $this->assertEquals($album3->id, $result[0]['albums'][1]->id);
        $this->assertEquals($album1->id, $result[0]['albums'][2]->id);
    }

    /** @test */
    public function group_albums_by_type_falls_back_to_release_year()
    {
        $artist = Artist::factory()->create();

        $album1 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => null,
            'release_year' => 2019,
        ]);

        $album2 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => null,
            'release_year' => 2022,
        ]);

        $albums = $artist->albums;

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        // Should be sorted by year: 2022, 2019
        $this->assertEquals($album2->id, $result[0]['albums'][0]->id);
        $this->assertEquals($album1->id, $result[0]['albums'][1]->id);
    }

    /** @test */
    public function group_albums_by_type_prefers_release_date_over_year()
    {
        $artist = Artist::factory()->create();

        $album1 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => '2020-12-31', // Late 2020
            'release_year' => 2020,
        ]);

        $album2 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => '2020-01-01', // Early 2020
            'release_year' => 2020,
        ]);

        $albums = $artist->albums;

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        // Should be sorted by date: 2020-12-31, 2020-01-01
        $this->assertEquals($album1->id, $result[0]['albums'][0]->id);
        $this->assertEquals($album2->id, $result[0]['albums'][1]->id);
    }

    /** @test */
    public function group_albums_by_type_sorts_nulls_last()
    {
        $artist = Artist::factory()->create();

        $album1 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => '2020-01-01',
            'release_year' => 2020,
        ]);

        $album2 = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'album',
            'release_date' => null,
            'release_year' => null, // No date info
        ]);

        $albums = $artist->albums;

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        // Album with date should be first, null date last
        $this->assertEquals($album1->id, $result[0]['albums'][0]->id);
        $this->assertEquals($album2->id, $result[0]['albums'][1]->id);
    }

    /** @test */
    public function group_albums_by_type_follows_display_order()
    {
        $artist = Artist::factory()->create();

        // Create in reverse order of display preference
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'other']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'remix']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'soundtrack']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'compilation']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'live']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'single']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'ep']);
        Album::factory()->create(['artist_id' => $artist->id, 'album_type' => 'album']);

        $albums = $artist->albums;

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        // Verify display order
        $this->assertEquals('Albums', $result[0]['label']);
        $this->assertEquals('EPs', $result[1]['label']);
        $this->assertEquals('Singles', $result[2]['label']);
        $this->assertEquals('Live Albums', $result[3]['label']);
        $this->assertEquals('Compilations', $result[4]['label']);
        $this->assertEquals('Soundtracks', $result[5]['label']);
        $this->assertEquals('Remix Albums', $result[6]['label']);
        $this->assertEquals('Other Releases', $result[7]['label']);
    }

    /** @test */
    public function group_albums_by_type_returns_empty_array_when_no_albums()
    {
        $albums = collect([]);

        $method = $this->reflection->getMethod('groupAlbumsByType');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $albums);

        $this->assertEmpty($result);
    }

    /** @test */
    public function deduplicate_links_keeps_one_per_type()
    {
        $artist = Artist::factory()->create();

        ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'spotify',
            'url' => 'https://open.spotify.com/artist/1',
        ]);

        ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'spotify',
            'url' => 'https://open.spotify.com/artist/2',
        ]);

        ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'youtube',
            'url' => 'https://youtube.com/channel/1',
        ]);

        $links = $artist->links;

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $links);

        $this->assertCount(2, $result); // 1 spotify, 1 youtube
    }

    /** @test */
    public function deduplicate_links_prefers_official_links()
    {
        $artist = Artist::factory()->create();

        $unofficial = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'spotify',
            'url' => 'https://open.spotify.com/artist/unofficial',
            'is_official' => false,
        ]);

        $official = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'spotify',
            'url' => 'https://open.spotify.com/artist/official',
            'is_official' => true,
        ]);

        $links = $artist->links;

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $links);

        $this->assertCount(1, $result);
        $this->assertEquals($official->id, $result[0]->id);
        $this->assertStringContainsString('official', $result[0]->url);
    }

    /** @test */
    public function deduplicate_links_prefers_us_apple_music_urls()
    {
        $artist = Artist::factory()->create();

        $jp = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'apple_music',
            'url' => 'https://music.apple.com/jp/artist/123',
        ]);

        $us = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'apple_music',
            'url' => 'https://music.apple.com/us/artist/123',
        ]);

        $artist->load('links'); // Refresh the links relationship

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist->links);

        $this->assertCount(1, $result);
        $this->assertEquals($us->id, $result[0]->id);
        $this->assertStringContainsString('/us/', $result[0]->url);
    }

    /** @test */
    public function deduplicate_links_deprioritizes_non_us_apple_music_urls()
    {
        $artist = Artist::factory()->create();

        $generic = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'apple_music',
            'url' => 'https://music.apple.com/artist/123', // No region
        ]);

        $fr = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'apple_music',
            'url' => 'https://music.apple.com/fr/artist/123',
        ]);

        $links = $artist->links;

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $links);

        $this->assertCount(1, $result);
        // Generic URL should win over /fr/
        $this->assertEquals($generic->id, $result[0]->id);
    }

    /** @test */
    public function deduplicate_links_prefers_shorter_urls()
    {
        $artist = Artist::factory()->create();

        $long = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'website',
            'url' => 'https://example.com/very/long/path/to/artist/page',
        ]);

        $short = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'website',
            'url' => 'https://example.com',
        ]);

        $links = $artist->links;

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $links);

        $this->assertCount(1, $result);
        $this->assertEquals($short->id, $result[0]->id);
    }

    /** @test */
    public function deduplicate_links_official_overrides_url_length()
    {
        $artist = Artist::factory()->create();

        $shortUnofficial = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'website',
            'url' => 'https://short.com',
            'is_official' => false,
        ]);

        $longOfficial = ArtistLink::factory()->create([
            'artist_id' => $artist->id,
            'type' => 'website',
            'url' => 'https://official-website-with-very-long-url.com/artist/official/page',
            'is_official' => true,
        ]);

        $links = $artist->links;

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $links);

        $this->assertCount(1, $result);
        // Official should win despite being longer
        $this->assertEquals($longOfficial->id, $result[0]->id);
    }

    /** @test */
    public function deduplicate_links_returns_empty_when_no_links()
    {
        $links = collect([]);

        $method = $this->reflection->getMethod('deduplicateLinks');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $links);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function build_seo_data_includes_required_fields()
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('ogType', $result);
        $this->assertArrayHasKey('ogImage', $result);
        $this->assertArrayHasKey('canonical', $result);
        $this->assertArrayHasKey('jsonLd', $result);

        $this->assertStringContainsString('The Beatles', $result['title']);
        $this->assertEquals('music.musician', $result['ogType']);
    }

    /** @test */
    public function build_seo_data_includes_genres_in_description()
    {
        $artist = Artist::factory()->create([
            'name' => 'The Beatles',
            'description' => null, // Force generated description
        ]);
        $genres = Genre::factory()->count(3)->create();
        $artist->genres()->attach($genres);
        $artist->load('genres');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertStringContainsString($genres[0]->name, $result['description']);
        $this->assertStringContainsString('artist', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_country_in_description()
    {
        $country = Country::factory()->create(['name' => 'United Kingdom']);
        $artist = Artist::factory()->create(['country_id' => $country->id]);
        $artist->load('country');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertStringContainsString('from United Kingdom', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_formed_year_in_description()
    {
        $artist = Artist::factory()->create([
            'formed_year' => 1960,
            'description' => null, // Force generated description
        ]);

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertStringContainsString('formed in 1960', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_album_count_in_description()
    {
        $artist = Artist::factory()->create(['description' => null]); // Force generated description
        Album::factory()->count(5)->create(['artist_id' => $artist->id]);
        $artist->load('albums');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertStringContainsString('with 5 releases', $result['description']);
    }

    /** @test */
    public function build_seo_data_uses_singular_release_for_one_album()
    {
        $artist = Artist::factory()->create(['description' => null]); // Force generated description
        Album::factory()->create(['artist_id' => $artist->id]);
        $artist->load('albums');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertStringContainsString('with 1 release', $result['description']);
        $this->assertStringNotContainsString('releases', $result['description']);
    }

    /** @test */
    public function build_seo_data_prefers_artist_description()
    {
        $artist = Artist::factory()->create([
            'name' => 'The Beatles',
            'description' => 'Custom description for SEO purposes.',
        ]);

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertStringContainsString('Custom description for SEO', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_og_image_when_present()
    {
        $artist = Artist::factory()->create([
            'image_commons' => 'The_Beatles_in_1964.jpg',
        ]);

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertNotNull($result['ogImage']);
        $this->assertStringContainsString('commons.wikimedia.org', $result['ogImage']);
        $this->assertStringContainsString('The_Beatles_in_1964.jpg', $result['ogImage']);
    }

    /** @test */
    public function build_seo_data_og_image_is_null_when_no_image()
    {
        $artist = Artist::factory()->create(['image_commons' => null]);

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $artist);

        $this->assertNull($result['ogImage']);
    }
}
