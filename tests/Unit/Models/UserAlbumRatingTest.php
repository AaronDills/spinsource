<?php

namespace Tests\Unit\Models;

use App\Models\Album;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAlbumRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_album_rating_factory_creates_valid_model(): void
    {
        $rating = UserAlbumRating::factory()->create();

        $this->assertNotNull($rating->id);
        $this->assertNotNull($rating->user_id);
        $this->assertNotNull($rating->album_id);
        $this->assertNotNull($rating->rating);
        $this->assertGreaterThanOrEqual(1, $rating->rating);
        $this->assertLessThanOrEqual(10, $rating->rating);
    }

    public function test_user_album_rating_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $this->assertInstanceOf(User::class, $rating->user);
        $this->assertEquals($user->id, $rating->user->id);
    }

    public function test_user_album_rating_belongs_to_album(): void
    {
        $album = Album::factory()->create();
        $rating = UserAlbumRating::factory()->forAlbum($album)->create();

        $this->assertInstanceOf(Album::class, $rating->album);
        $this->assertEquals($album->id, $rating->album->id);
    }

    public function test_for_user_factory_state(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $this->assertEquals($user->id, $rating->user_id);
    }

    public function test_for_album_factory_state(): void
    {
        $album = Album::factory()->create();
        $rating = UserAlbumRating::factory()->forAlbum($album)->create();

        $this->assertEquals($album->id, $rating->album_id);
    }

    public function test_with_rating_factory_state(): void
    {
        $rating = UserAlbumRating::factory()->withRating(8)->create();

        $this->assertEquals(8, $rating->rating);
    }

    public function test_listened_at_cast(): void
    {
        $rating = UserAlbumRating::factory()->create([
            'listened_at' => '2024-01-15',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $rating->listened_at);
        $this->assertEquals('2024-01-15', $rating->listened_at->toDateString());
    }

    public function test_notes_can_be_null(): void
    {
        $rating = UserAlbumRating::factory()->create([
            'notes' => null,
        ]);

        $this->assertNull($rating->notes);
    }

    public function test_notes_can_have_content(): void
    {
        $rating = UserAlbumRating::factory()->create([
            'notes' => 'Great album, loved the guitar work!',
        ]);

        $this->assertEquals('Great album, loved the guitar work!', $rating->notes);
    }
}
