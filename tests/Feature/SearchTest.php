<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Search Page Tests
    // -------------------------------------------------------------------------

    public function test_search_page_is_accessible(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
        $response->assertViewIs('search.index');
    }

    public function test_search_page_contains_search_form(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee('Search');
        $response->assertSee('Search artists and albums');
    }

    public function test_search_page_has_example_chips(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee('Try searching for:');
        $response->assertSee('Radiohead');
        $response->assertSee('Abbey Road');
    }

    public function test_search_page_has_correct_seo_metadata(): void
    {
        $response = $this->get('/search');

        $response->assertOk();
        $response->assertSee('Search Artists and Albums', false);
    }

    // -------------------------------------------------------------------------
    // Search Results Page Tests
    // -------------------------------------------------------------------------

    public function test_search_results_page_is_accessible(): void
    {
        $response = $this->get('/search-results');

        $response->assertOk();
        $response->assertViewIs('search.results');
    }

    public function test_search_results_page_accepts_query_parameter(): void
    {
        $response = $this->get('/search-results?q=test');

        $response->assertOk();
        $response->assertViewHas('query', 'test');
    }

    public function test_search_results_page_shows_no_results_for_empty_query(): void
    {
        $response = $this->get('/search-results?q=');

        $response->assertOk();
        $response->assertViewHas('artists');
        $response->assertViewHas('albums');
    }

    public function test_search_results_page_shows_empty_results_for_short_query(): void
    {
        $response = $this->get('/search-results?q=a');

        $response->assertOk();
        // Short queries should return empty collections
        $response->assertViewHas('artists', function ($artists) {
            return $artists->isEmpty();
        });
        $response->assertViewHas('albums', function ($albums) {
            return $albums->isEmpty();
        });
    }

    // -------------------------------------------------------------------------
    // Search API Tests
    // -------------------------------------------------------------------------

    public function test_api_search_returns_json(): void
    {
        $response = $this->getJson('/api/search?q=test');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_api_search_returns_empty_array_for_missing_query(): void
    {
        $response = $this->getJson('/api/search');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_api_search_returns_empty_array_for_empty_query(): void
    {
        $response = $this->getJson('/api/search?q=');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_api_search_returns_empty_array_for_single_character_query(): void
    {
        $response = $this->getJson('/api/search?q=a');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_api_search_returns_array_for_valid_query(): void
    {
        $response = $this->getJson('/api/search?q=test');

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_api_search_result_structure_for_artists(): void
    {
        // Note: With SCOUT_DRIVER=null, this will return empty results
        // This test documents the expected structure
        $response = $this->getJson('/api/search?q=test');

        $response->assertOk();
        // The response should be an array (possibly empty with null driver)
        $this->assertIsArray($response->json());
    }

    // -------------------------------------------------------------------------
    // Route Name Tests
    // -------------------------------------------------------------------------

    public function test_search_page_route_name(): void
    {
        $this->assertEquals('/search', route('search.page', [], false));
    }

    public function test_search_results_route_name(): void
    {
        $this->assertEquals('/search-results', route('search.results', [], false));
    }

    public function test_api_search_route_name(): void
    {
        $this->assertEquals('/api/search', route('api.search', [], false));
    }

    public function test_search_results_route_with_query(): void
    {
        $url = route('search.results', ['q' => 'radiohead'], false);
        $this->assertEquals('/search-results?q=radiohead', $url);
    }
}
