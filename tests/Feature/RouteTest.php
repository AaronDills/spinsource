<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_route_exists(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_home_route_has_name(): void
    {
        $this->assertEquals('/', route('home', [], false));
    }

    public function test_dashboard_route_requires_auth(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_route_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_account_route_requires_auth(): void
    {
        $response = $this->get('/account');

        $response->assertRedirect('/login');
    }

    public function test_account_route_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account');

        $response->assertOk();
    }

    public function test_account_reviews_route_requires_auth(): void
    {
        $response = $this->get('/account/reviews');

        $response->assertRedirect('/login');
    }

    public function test_account_reviews_route_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/reviews');

        $response->assertOk();
    }

    public function test_account_statistics_route_requires_auth(): void
    {
        $response = $this->get('/account/statistics');

        $response->assertRedirect('/login');
    }

    public function test_account_statistics_route_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/account/statistics');

        $response->assertOk();
    }

    public function test_account_review_edit_route_requires_auth(): void
    {
        $rating = UserAlbumRating::factory()->create();

        $response = $this->get("/account/reviews/{$rating->id}/edit");

        $response->assertRedirect('/login');
    }

    public function test_account_review_edit_route_accessible_by_owner(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $response = $this
            ->actingAs($user)
            ->get("/account/reviews/{$rating->id}/edit");

        $response->assertOk();
    }

    public function test_account_review_update_route_requires_auth(): void
    {
        $rating = UserAlbumRating::factory()->create();

        $response = $this->patch("/account/reviews/{$rating->id}", [
            'rating' => 5,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_account_review_delete_route_requires_auth(): void
    {
        $rating = UserAlbumRating::factory()->create();

        $response = $this->delete("/account/reviews/{$rating->id}");

        $response->assertRedirect('/login');
    }

    public function test_profile_route_requires_auth(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_profile_route_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_all_named_routes_exist(): void
    {
        $routes = [
            'home',
            'dashboard',
            'account',
            'account.reviews',
            'account.statistics',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'login',
            'register',
            'logout',
        ];

        foreach ($routes as $routeName) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($routeName),
                "Route [{$routeName}] is not defined."
            );
        }
    }
}
