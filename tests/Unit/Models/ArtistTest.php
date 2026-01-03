<?php

namespace Tests\Unit\Models;

use App\Models\Album;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_factory_creates_valid_model(): void
    {
        $artist = Artist::factory()->create();

        $this->assertNotNull($artist->id);
        $this->assertNotEmpty($artist->name);
        $this->assertNotEmpty($artist->wikidata_qid);
        $this->assertStringStartsWith('Q', $artist->wikidata_qid);
    }

    public function test_artist_belongs_to_country(): void
    {
        $country = Country::factory()->create();
        $artist = Artist::factory()->create(['country_id' => $country->id]);

        $this->assertInstanceOf(Country::class, $artist->country);
        $this->assertEquals($country->id, $artist->country->id);
    }

    public function test_artist_has_many_albums(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->count(3)->create(['artist_id' => $artist->id]);

        $this->assertCount(3, $artist->albums);
        $this->assertInstanceOf(Album::class, $artist->albums->first());
    }

    public function test_artist_has_many_links(): void
    {
        $artist = Artist::factory()->create();
        ArtistLink::factory()->count(2)->forArtist($artist)->create();

        $this->assertCount(2, $artist->links);
        $this->assertInstanceOf(ArtistLink::class, $artist->links->first());
    }

    public function test_artist_belongs_to_many_genres(): void
    {
        $artist = Artist::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $artist->genres()->attach($genres->pluck('id'));

        $this->assertCount(2, $artist->genres);
        $this->assertInstanceOf(Genre::class, $artist->genres->first());
    }

    public function test_has_wikipedia_attribute(): void
    {
        $artistWithWikipedia = Artist::factory()->create([
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Test_Artist',
        ]);
        $artistWithoutWikipedia = Artist::factory()->create([
            'wikipedia_url' => null,
        ]);

        $this->assertTrue($artistWithWikipedia->has_wikipedia);
        $this->assertFalse($artistWithoutWikipedia->has_wikipedia);
    }

    public function test_rank_score_attribute(): void
    {
        $artistWithSignals = Artist::factory()->create([
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Test',
            'album_count' => 10,
            'link_count' => 5,
            'spotify_artist_id' => 'abc123',
            'musicbrainz_artist_mbid' => fake()->uuid(),
        ]);

        $artistWithoutSignals = Artist::factory()->create([
            'wikipedia_url' => null,
            'album_count' => 0,
            'link_count' => 0,
            'spotify_artist_id' => null,
            'musicbrainz_artist_mbid' => null,
        ]);

        $this->assertGreaterThan($artistWithoutSignals->rank_score, $artistWithSignals->rank_score);
        $this->assertGreaterThan(0, $artistWithSignals->rank_score);
    }

    public function test_with_country_factory_state(): void
    {
        $artist = Artist::factory()->withCountry()->create();

        $this->assertNotNull($artist->country_id);
        $this->assertInstanceOf(Country::class, $artist->country);
    }

    public function test_disbanded_factory_state(): void
    {
        $artist = Artist::factory()
            ->state(['formed_year' => 1980])
            ->disbanded()
            ->create();

        $this->assertNotNull($artist->disbanded_year);
        $this->assertGreaterThanOrEqual($artist->formed_year, $artist->disbanded_year);
    }

    public function test_searchable_array_structure(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'sort_name' => 'Artist, Test',
        ]);

        $searchable = $artist->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertArrayHasKey('name', $searchable);
        $this->assertArrayHasKey('sort_name', $searchable);
        $this->assertArrayHasKey('rank_score', $searchable);
    }
}
