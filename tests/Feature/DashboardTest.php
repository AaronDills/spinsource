<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_page_is_displayed_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_dashboard_displays_user_name(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Welcome back, John Doe');
    }

    public function test_dashboard_displays_stats_for_user_with_no_ratings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Albums Rated');
        $response->assertSee('Average Rating');
    }

    public function test_dashboard_displays_recent_ratings(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $album = Album::factory()->create([
            'title' => 'Test Album',
            'artist_id' => $artist->id,
        ]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->withRating(8)->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Test Album');
        $response->assertSee('Test Artist');
        $response->assertSee('8/10');
    }

    public function test_dashboard_displays_top_rated_albums(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Top Artist']);
        $album = Album::factory()->create([
            'title' => 'Top Rated Album',
            'artist_id' => $artist->id,
        ]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->withRating(10)->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Top Rated Album');
        $response->assertSee('Your Top Rated Albums');
    }

    public function test_dashboard_displays_correct_stats(): void
    {
        $user = User::factory()->create();

        // Create 5 ratings with ratings 6, 7, 8, 9, 10
        for ($i = 6; $i <= 10; $i++) {
            UserAlbumRating::factory()->forUser($user)->withRating($i)->create();
        }

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        // Total ratings should be 5
        $response->assertSee('>5<', false); // Total ratings
        // Average should be 8.0
        $response->assertSee('>8<', false);
    }

    public function test_dashboard_shows_admin_link_for_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this
            ->actingAs($admin)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Admin Panel');
    }

    public function test_dashboard_hides_admin_link_for_regular_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Admin Panel');
    }

    public function test_dashboard_has_quick_action_links(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Search Music');
        $response->assertSee('My Reviews');
        $response->assertSee('Settings');
    }

    public function test_dashboard_shows_empty_state_when_no_ratings(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('No ratings yet');
        $response->assertSee('Discover Music');
    }
}
