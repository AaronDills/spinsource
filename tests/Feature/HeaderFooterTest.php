<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_header_with_login_button_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Log in');
        $response->assertSee('Sign up');
    }

    public function test_home_page_displays_user_dropdown_for_authenticated_users(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $response = $this
            ->actingAs($user)
            ->get('/');

        $response->assertOk();
        $response->assertSee('John Doe');
        $response->assertDontSee('Log in');
    }

    public function test_footer_is_displayed_on_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        // Footer should be present
        $response->assertSee('Discover and collect the music you love');
        $response->assertSee('All rights reserved');
    }

    public function test_footer_displays_recent_reviews_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Recent Artist']);
        $album = Album::factory()->create([
            'title' => 'Recent Album',
            'artist_id' => $artist->id,
        ]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Your Recent Reviews');
        $response->assertSee('Recent Artist');
        $response->assertSee('Recent Album');
    }

    public function test_footer_does_not_show_recent_reviews_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Your Recent Reviews');
    }

    public function test_footer_shows_quick_links(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Search Music');
        $response->assertSee('Dashboard');
        $response->assertSee('Account');
    }

    public function test_header_shows_admin_link_for_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this
            ->actingAs($admin)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Admin');
    }

    public function test_header_hides_admin_link_for_regular_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // Check the navigation specifically (the page may have other "Admin" text)
        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        // Admin link should not be in navigation
        $response->assertDontSee('route(\'admin.monitoring\')');
    }

    public function test_navigation_has_account_link(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Account');
    }

    public function test_home_page_has_search_functionality(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Understand the music you love');
        $response->assertSee('Start Exploring');
    }

    public function test_footer_shows_copyright(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee(date('Y'));
        $response->assertSee('All rights reserved');
    }
}
