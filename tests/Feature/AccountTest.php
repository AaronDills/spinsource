<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_requires_authentication(): void
    {
        $response = $this->get('/account');

        $response->assertRedirect('/login');
    }

    public function test_account_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account');

        $response->assertOk();
    }

    public function test_account_page_displays_user_info(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/account');

        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertSee('jane@example.com');
    }

    public function test_account_page_displays_stats(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->count(3)->forUser($user)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account');

        $response->assertOk();
        $response->assertSee('Quick Stats');
        $response->assertSee('Total Reviews');
    }

    public function test_account_page_displays_recent_reviews(): void
    {
        $user = User::factory()->create();
        $album = Album::factory()->create(['title' => 'My Album']);
        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account');

        $response->assertOk();
        $response->assertSee('Recent Reviews');
        $response->assertSee('My Album');
    }

    public function test_reviews_page_requires_authentication(): void
    {
        $response = $this->get('/account/reviews');

        $response->assertRedirect('/login');
    }

    public function test_reviews_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/reviews');

        $response->assertOk();
    }

    public function test_reviews_page_displays_all_user_reviews(): void
    {
        $user = User::factory()->create();
        $album = Album::factory()->create(['title' => 'Reviewed Album']);
        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/reviews');

        $response->assertOk();
        $response->assertSee('Reviewed Album');
    }

    public function test_reviews_page_can_sort_by_highest_rating(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(5)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(10)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/reviews?sort=highest');

        $response->assertOk();
        $response->assertSee('Highest Rated');
    }

    public function test_reviews_page_can_filter_by_rating(): void
    {
        $user = User::factory()->create();
        $album1 = Album::factory()->create(['title' => 'UniqueAlbumForFilterTestLow']);
        $album2 = Album::factory()->create(['title' => 'UniqueAlbumForFilterTestHigh']);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album1)->withRating(3)->create();
        UserAlbumRating::factory()->forUser($user)->forAlbum($album2)->withRating(9)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/reviews?rating=9');

        $response->assertOk();
        $response->assertSee('UniqueAlbumForFilterTestHigh');
        // The grid view shows only filtered results
        $this->assertStringNotContainsString('UniqueAlbumForFilterTestLow',
            preg_replace('/<footer.*<\/footer>/s', '', $response->getContent()));
    }

    public function test_edit_review_page_requires_authentication(): void
    {
        $rating = UserAlbumRating::factory()->create();

        $response = $this->get("/account/reviews/{$rating->id}/edit");

        $response->assertRedirect('/login');
    }

    public function test_edit_review_page_is_displayed(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $response = $this
            ->actingAs($user)
            ->get("/account/reviews/{$rating->id}/edit");

        $response->assertOk();
    }

    public function test_cannot_edit_other_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($otherUser)->create();

        $response = $this
            ->actingAs($user)
            ->get("/account/reviews/{$rating->id}/edit");

        $response->assertForbidden();
    }

    public function test_review_can_be_updated(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->withRating(5)->create();

        $response = $this
            ->actingAs($user)
            ->patch("/account/reviews/{$rating->id}", [
                'rating' => 8,
                'notes' => 'Updated review notes',
                'listened_at' => '2024-01-15',
            ]);

        $response->assertRedirect('/account/reviews');
        $response->assertSessionHas('status', 'Review updated successfully.');

        $rating->refresh();
        $this->assertEquals(8, $rating->rating);
        $this->assertEquals('Updated review notes', $rating->notes);
        $this->assertEquals('2024-01-15', $rating->listened_at->toDateString());
    }

    public function test_update_review_validates_rating(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $response = $this
            ->actingAs($user)
            ->patch("/account/reviews/{$rating->id}", [
                'rating' => 15, // Invalid - max is 10
            ]);

        $response->assertSessionHasErrors('rating');
    }

    public function test_cannot_update_other_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($otherUser)->withRating(5)->create();

        $response = $this
            ->actingAs($user)
            ->patch("/account/reviews/{$rating->id}", [
                'rating' => 8,
            ]);

        $response->assertForbidden();
        $this->assertEquals(5, $rating->fresh()->rating);
    }

    public function test_review_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $response = $this
            ->actingAs($user)
            ->delete("/account/reviews/{$rating->id}");

        $response->assertRedirect('/account/reviews');
        $response->assertSessionHas('status', 'Review deleted successfully.');
        $this->assertNull($rating->fresh());
    }

    public function test_cannot_delete_other_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($otherUser)->create();

        $response = $this
            ->actingAs($user)
            ->delete("/account/reviews/{$rating->id}");

        $response->assertForbidden();
        $this->assertNotNull($rating->fresh());
    }

    public function test_statistics_page_requires_authentication(): void
    {
        $response = $this->get('/account/statistics');

        $response->assertRedirect('/login');
    }

    public function test_statistics_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/statistics');

        $response->assertOk();
    }

    public function test_statistics_page_displays_rating_distribution(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(7)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(8)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(8)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/statistics');

        $response->assertOk();
        $response->assertSee('Rating Distribution');
    }

    public function test_statistics_page_displays_top_artists(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Favorite Artist']);
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/statistics');

        $response->assertOk();
        $response->assertSee('Most Reviewed Artists');
        $response->assertSee('Favorite Artist');
    }
}
