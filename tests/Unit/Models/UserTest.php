<?php

namespace Tests\Unit\Models;

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_ratings_relationship(): void
    {
        $user = User::factory()->create();
        $album = Album::factory()->create();

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $this->assertCount(1, $user->ratings);
        $this->assertInstanceOf(UserAlbumRating::class, $user->ratings->first());
    }

    public function test_user_can_have_multiple_ratings(): void
    {
        $user = User::factory()->create();

        UserAlbumRating::factory()->count(5)->forUser($user)->create();

        $this->assertCount(5, $user->ratings);
    }

    public function test_recent_ratings_returns_limited_results(): void
    {
        $user = User::factory()->create();

        UserAlbumRating::factory()->count(10)->forUser($user)->create();

        $recentRatings = $user->recentRatings(5);

        $this->assertCount(5, $recentRatings);
    }

    public function test_recent_ratings_returns_latest_first(): void
    {
        $user = User::factory()->create();

        $oldRating = UserAlbumRating::factory()->forUser($user)->create([
            'created_at' => now()->subDays(5),
        ]);
        $newRating = UserAlbumRating::factory()->forUser($user)->create([
            'created_at' => now(),
        ]);

        $recentRatings = $user->recentRatings(2);

        $this->assertEquals($newRating->id, $recentRatings->first()->id);
        $this->assertEquals($oldRating->id, $recentRatings->last()->id);
    }

    public function test_recent_ratings_loads_album_and_artist(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $recentRatings = $user->recentRatings(1);

        $this->assertTrue($recentRatings->first()->relationLoaded('album'));
        $this->assertTrue($recentRatings->first()->album->relationLoaded('artist'));
    }

    public function test_recently_reviewed_artists_returns_unique_artists(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $album1 = Album::factory()->create(['artist_id' => $artist->id]);
        $album2 = Album::factory()->create(['artist_id' => $artist->id]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album1)->create();
        UserAlbumRating::factory()->forUser($user)->forAlbum($album2)->create();

        $recentArtists = $user->recentlyReviewedArtists(5);

        // Should only have 1 unique artist
        $this->assertCount(1, $recentArtists);
        $this->assertEquals($artist->id, $recentArtists->first()->id);
    }

    public function test_recently_reviewed_artists_limits_results(): void
    {
        $user = User::factory()->create();

        // Create 10 different artists with albums and ratings
        for ($i = 0; $i < 10; $i++) {
            $artist = Artist::factory()->create();
            $album = Album::factory()->create(['artist_id' => $artist->id]);
            UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();
        }

        $recentArtists = $user->recentlyReviewedArtists(3);

        $this->assertCount(3, $recentArtists);
    }

    public function test_recently_reviewed_albums_returns_albums(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $recentAlbums = $user->recentlyReviewedAlbums(5);

        $this->assertCount(1, $recentAlbums);
        $this->assertEquals($album->id, $recentAlbums->first()->id);
    }

    public function test_recently_reviewed_albums_limits_results(): void
    {
        $user = User::factory()->create();

        UserAlbumRating::factory()->count(10)->forUser($user)->create();

        $recentAlbums = $user->recentlyReviewedAlbums(3);

        $this->assertCount(3, $recentAlbums);
    }

    public function test_is_admin_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->assertTrue($user->is_admin);
        $this->assertIsBool($user->is_admin);
    }

    public function test_user_without_ratings_returns_empty_collections(): void
    {
        $user = User::factory()->create();

        $this->assertCount(0, $user->ratings);
        $this->assertCount(0, $user->recentRatings(5));
        $this->assertCount(0, $user->recentlyReviewedArtists(5));
        $this->assertCount(0, $user->recentlyReviewedAlbums(5));
    }
}
