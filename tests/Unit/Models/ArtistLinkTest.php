<?php

namespace Tests\Unit\Models;

use App\Models\Artist;
use App\Models\ArtistLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_link_factory_creates_valid_model(): void
    {
        $link = ArtistLink::factory()->create();

        $this->assertNotNull($link->id);
        $this->assertNotNull($link->artist_id);
        $this->assertNotEmpty($link->type);
        $this->assertNotEmpty($link->url);
    }

    public function test_artist_link_belongs_to_artist(): void
    {
        $artist = Artist::factory()->create();
        $link = ArtistLink::factory()->forArtist($artist)->create();

        $this->assertInstanceOf(Artist::class, $link->artist);
        $this->assertEquals($artist->id, $link->artist->id);
    }

    public function test_official_factory_state(): void
    {
        $link = ArtistLink::factory()->official()->create();

        $this->assertTrue($link->is_official);
    }

    public function test_spotify_factory_state(): void
    {
        $link = ArtistLink::factory()->spotify()->create();

        $this->assertEquals('spotify', $link->type);
        $this->assertStringContainsString('open.spotify.com', $link->url);
    }

    public function test_is_official_cast(): void
    {
        $link = ArtistLink::factory()->create(['is_official' => 1]);

        $this->assertIsBool($link->is_official);
        $this->assertTrue($link->is_official);
    }
}
