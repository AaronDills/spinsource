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

    public function test_compute_quality_score_with_all_signals(): void
    {
        $data = [
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Test',
            'description' => 'A test artist description.',
            'image_commons' => 'Test_Artist.jpg',
            'official_website' => 'https://example.com',
            'spotify_artist_id' => 'abc123',
            'apple_music_artist_id' => '12345',
            'musicbrainz_artist_mbid' => fake()->uuid(),
            'discogs_artist_id' => '67890',
            'album_count' => 50,
            'link_count' => 10,
        ];

        $score = Artist::computeQualityScore($data);

        // Expected: 15 + 5 + 5 + 3 + 10 + 5 + 3 + 2 + min(25, 6*ln(51)) + min(10, 2*ln(11))
        // = 48 + ~23.5 + ~4.8 = ~76
        $this->assertGreaterThan(70, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_compute_quality_score_with_no_signals(): void
    {
        $data = [
            'wikipedia_url' => null,
            'description' => null,
            'image_commons' => null,
            'official_website' => null,
            'spotify_artist_id' => null,
            'apple_music_artist_id' => null,
            'musicbrainz_artist_mbid' => null,
            'discogs_artist_id' => null,
            'album_count' => 0,
            'link_count' => 0,
        ];

        $score = Artist::computeQualityScore($data);

        // Minimum score should be 0
        $this->assertEquals(0, $score);
    }

    public function test_compute_quality_score_wikipedia_is_strong_signal(): void
    {
        $withWikipedia = Artist::computeQualityScore([
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Test',
            'album_count' => 0,
            'link_count' => 0,
        ]);

        $withoutWikipedia = Artist::computeQualityScore([
            'wikipedia_url' => null,
            'album_count' => 0,
            'link_count' => 0,
        ]);

        $this->assertEquals(15, $withWikipedia - $withoutWikipedia);
    }

    public function test_compute_quality_score_spotify_is_strong_signal(): void
    {
        $withSpotify = Artist::computeQualityScore([
            'spotify_artist_id' => 'abc123',
            'album_count' => 0,
            'link_count' => 0,
        ]);

        $withoutSpotify = Artist::computeQualityScore([
            'spotify_artist_id' => null,
            'album_count' => 0,
            'link_count' => 0,
        ]);

        $this->assertEquals(10, $withSpotify - $withoutSpotify);
    }

    public function test_compute_quality_score_album_count_has_diminishing_returns(): void
    {
        $score1Album = Artist::computeQualityScore(['album_count' => 1, 'link_count' => 0]);
        $score10Albums = Artist::computeQualityScore(['album_count' => 10, 'link_count' => 0]);
        $score100Albums = Artist::computeQualityScore(['album_count' => 100, 'link_count' => 0]);
        $score500Albums = Artist::computeQualityScore(['album_count' => 500, 'link_count' => 0]);

        // More albums should mean higher score
        $this->assertGreaterThan($score1Album, $score10Albums);
        $this->assertGreaterThan($score10Albums, $score100Albums);

        // But diminishing returns - the difference between 100 and 500 should be less than 1 and 100
        $diff1to100 = $score100Albums - $score1Album;
        $diff100to500 = $score500Albums - $score100Albums;

        $this->assertGreaterThan($diff100to500, $diff1to100);
    }

    public function test_quality_score_is_capped_at_100(): void
    {
        // Create data that would exceed 100 if not capped
        $data = [
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Test',
            'description' => 'Description',
            'image_commons' => 'Image.jpg',
            'official_website' => 'https://example.com',
            'spotify_artist_id' => 'abc123',
            'apple_music_artist_id' => '12345',
            'musicbrainz_artist_mbid' => fake()->uuid(),
            'discogs_artist_id' => '67890',
            'album_count' => 1000, // Would give max 25
            'link_count' => 1000, // Would give max 10
        ];

        $score = Artist::computeQualityScore($data);

        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_stored_quality_score_used_in_searchable_array(): void
    {
        $artist = Artist::factory()->create([
            'quality_score' => 75,
        ]);

        $searchable = $artist->toSearchableArray();

        $this->assertEquals(75, $searchable['rank_score']);
    }

    public function test_higher_quality_artists_rank_before_lower(): void
    {
        $lowQualityArtist = Artist::factory()->create([
            'name' => 'Unknown Band',
            'quality_score' => 5,
        ]);

        $highQualityArtist = Artist::factory()->create([
            'name' => 'Famous Artist',
            'quality_score' => 80,
        ]);

        $ranked = Artist::orderByDesc('quality_score')->pluck('name')->toArray();

        $this->assertEquals('Famous Artist', $ranked[0]);
        $this->assertEquals('Unknown Band', $ranked[1]);
    }
}
