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

    // -------------------------------------------------------------------------
    // Search Routes
    // -------------------------------------------------------------------------

    public function test_search_page_route_exists(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
    }

    public function test_search_page_returns_html(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
        // Accept either uppercase or lowercase charset
        $contentType = $response->headers->get('content-type');
        $this->assertStringContainsString('text/html', $contentType);
    }

    public function test_search_results_route_exists(): void
    {
        $response = $this->get('/search-results');

        $response->assertOk();
    }

    public function test_search_results_accepts_query_parameter(): void
    {
        $response = $this->get('/search-results?q=test');

        $response->assertOk();
    }

    // -------------------------------------------------------------------------
    // API Routes
    // -------------------------------------------------------------------------

    public function test_api_search_route_exists(): void
    {
        $response = $this->getJson('/api/search?q=test');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_api_search_returns_empty_array_for_short_query(): void
    {
        $response = $this->getJson('/api/search?q=a');

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_api_search_returns_array_structure(): void
    {
        $response = $this->getJson('/api/search?q=test');

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    // -------------------------------------------------------------------------
    // Admin Routes
    // -------------------------------------------------------------------------

    public function test_admin_monitoring_route_requires_auth(): void
    {
        $response = $this->get('/admin/monitoring');

        $response->assertRedirect('/login');
    }

    public function test_admin_logs_route_requires_auth(): void
    {
        $response = $this->get('/admin/logs');

        $response->assertRedirect('/login');
    }

    public function test_admin_jobs_route_requires_auth(): void
    {
        $response = $this->get('/admin/jobs');

        $response->assertRedirect('/login');
    }

    public function test_admin_api_routes_require_auth(): void
    {
        $apiRoutes = [
            ['GET', '/api/admin/monitoring/data'],
            ['POST', '/api/admin/monitoring/clear-failed'],
            ['GET', '/api/admin/logs/data'],
            ['GET', '/api/admin/logs/files'],
            ['GET', '/api/admin/jobs/data'],
            ['POST', '/api/admin/jobs/dispatch'],
            ['POST', '/api/admin/jobs/cancel'],
            ['POST', '/api/admin/jobs/failed/clear'],
            ['POST', '/api/admin/jobs/failed/retry'],
        ];

        foreach ($apiRoutes as [$method, $route]) {
            $response = $method === 'GET'
                ? $this->getJson($route)
                : $this->postJson($route);

            $response->assertUnauthorized();
        }
    }

    // -------------------------------------------------------------------------
    // Named Routes Exist
    // -------------------------------------------------------------------------

    public function test_all_named_routes_exist(): void
    {
        $routes = [
            // Public routes
            'home',
            'search.page',
            'search.results',
            // Auth routes
            'login',
            'register',
            'logout',
            // User routes
            'dashboard',
            'account',
            'account.reviews',
            'account.statistics',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            // Admin HTML routes
            'admin.monitoring',
            'admin.logs',
            'admin.jobs',
            // API routes
            'api.search',
            'api.admin.monitoring.data',
            'api.admin.monitoring.clear-failed',
            'api.admin.logs.data',
            'api.admin.logs.files',
            'api.admin.jobs.data',
            'api.admin.jobs.dispatch',
            'api.admin.jobs.cancel',
            'api.admin.jobs.failed.clear',
            'api.admin.jobs.failed.retry',
        ];

        foreach ($routes as $routeName) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($routeName),
                "Route [{$routeName}] is not defined."
            );
        }
    }
}
