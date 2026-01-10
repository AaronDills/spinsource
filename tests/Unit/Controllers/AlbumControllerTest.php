<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\AlbumController;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumControllerTest extends TestCase
{
    use RefreshDatabase;

    private AlbumController $controller;

    private \ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AlbumController();
        $this->reflection = new \ReflectionClass($this->controller);
    }

    /** @test */
    public function build_seo_data_includes_required_fields()
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);
        $album = Album::factory()->create([
            'title' => 'Abbey Road',
            'artist_id' => $artist->id,
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('ogType', $result);
        $this->assertArrayHasKey('ogImage', $result);
        $this->assertArrayHasKey('canonical', $result);
        $this->assertArrayHasKey('jsonLd', $result);

        $this->assertStringContainsString('Abbey Road', $result['title']);
        $this->assertStringContainsString('The Beatles', $result['title']);
        $this->assertEquals('music.album', $result['ogType']);
    }

    /** @test */
    public function build_seo_data_includes_album_type_in_description()
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'live',
            'description' => null,
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('Live', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_artist_name_in_description()
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'description' => null,
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('by The Beatles', $result['description']);
    }

    /** @test */
    public function build_seo_data_handles_missing_artist_relationship()
    {
        // Create album with artist, then unset the relationship
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $album->load('tracks');

        // Unset the artist relationship to simulate it being null
        $album->setRelation('artist', null);

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('Unknown Artist', $result['title']);
        $this->assertStringContainsString('by Unknown Artist', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_release_year_in_description()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'release_year' => 1969,
            'description' => null, // Ensure we generate description
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('(1969)', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_track_count_in_description()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'description' => null,
        ]);

        // Create tracks with unique positions
        for ($i = 1; $i <= 5; $i++) {
            Track::factory()->create([
                'album_id' => $album->id,
                'position' => $i,
                'disc_number' => 1,
            ]);
        }

        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('5 tracks', $result['description']);
    }

    /** @test */
    public function build_seo_data_uses_singular_track_for_one_track()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'description' => null,
        ]);
        Track::factory()->create([
            'album_id' => $album->id,
            'position' => 1,
            'disc_number' => 1,
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('1 track', $result['description']);
        $this->assertStringNotContainsString('tracks', $result['description']);
    }

    /** @test */
    public function build_seo_data_prefers_album_description()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'description' => 'Custom album description for SEO purposes.',
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertStringContainsString('Custom album description', $result['description']);
    }

    /** @test */
    public function build_seo_data_truncates_album_description()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'description' => str_repeat('Long description. ', 50), // Over 160 chars
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertLessThanOrEqual(160, mb_strlen($result['description']));
        $this->assertStringEndsWith('...', $result['description']);
    }

    /** @test */
    public function build_seo_data_includes_og_image_when_present()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'cover_image_commons' => 'Abbey_Road.jpg',
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertNotNull($result['ogImage']);
        $this->assertStringContainsString('Abbey_Road.jpg', $result['ogImage']);
    }

    /** @test */
    public function build_seo_data_og_image_is_null_when_no_cover()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'cover_image_commons' => null,
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        $this->assertNull($result['ogImage']);
    }

    /** @test */
    public function build_seo_data_formats_description_correctly()
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'title' => 'Abbey Road',
            'album_type' => 'album',
            'release_year' => 1969,
            'description' => null,
        ]);

        // Create tracks with unique positions
        for ($i = 1; $i <= 3; $i++) {
            Track::factory()->create([
                'album_id' => $album->id,
                'position' => $i,
                'disc_number' => 1,
            ]);
        }

        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        // Should contain: "Album by The Beatles (1969) - 3 tracks"
        $this->assertStringContainsString('Album', $result['description']);
        $this->assertStringContainsString('by The Beatles', $result['description']);
        $this->assertStringContainsString('(1969)', $result['description']);
        $this->assertStringContainsString('3 tracks', $result['description']);
    }

    /** @test */
    public function build_seo_data_capitalizes_album_type()
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'album_type' => 'ep',
            'description' => null,
        ]);
        $album->load('artist', 'tracks');

        $method = $this->reflection->getMethod('buildSeoData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $album);

        // Should be capitalized: "Ep" not "ep"
        $this->assertStringContainsString('Ep by', $result['description']);
    }
}
