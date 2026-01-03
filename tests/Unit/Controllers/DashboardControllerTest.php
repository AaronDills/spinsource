<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\DashboardController;
use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new DashboardController;
    }

    public function test_index_returns_view(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $this->assertEquals('dashboard', $response->getName());
    }

    public function test_index_passes_user_to_view(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $this->assertEquals($user->id, $response->getData()['user']->id);
    }

    public function test_index_calculates_stats(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->count(3)->forUser($user)->withRating(8)->create();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $stats = $response->getData()['stats'];
        $this->assertEquals(3, $stats['totalRatings']);
        $this->assertEquals(8.0, $stats['averageRating']);
    }

    public function test_index_returns_recent_ratings(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->count(15)->forUser($user)->create();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $this->assertCount(10, $response->getData()['recentRatings']);
    }

    public function test_index_returns_top_rated_albums(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(10)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(9)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(5)->create();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $topRatedAlbums = $response->getData()['topRatedAlbums'];
        $this->assertCount(3, $topRatedAlbums);
        // First should be highest rated
        $this->assertEquals(10, $topRatedAlbums->first()->rating);
    }

    public function test_index_calculates_unique_artists(): void
    {
        $user = User::factory()->create();
        $artist1 = Artist::factory()->create();
        $artist2 = Artist::factory()->create();

        $album1 = Album::factory()->create(['artist_id' => $artist1->id]);
        $album2 = Album::factory()->create(['artist_id' => $artist1->id]);
        $album3 = Album::factory()->create(['artist_id' => $artist2->id]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album1)->create();
        UserAlbumRating::factory()->forUser($user)->forAlbum($album2)->create();
        UserAlbumRating::factory()->forUser($user)->forAlbum($album3)->create();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $stats = $response->getData()['stats'];
        $this->assertEquals(2, $stats['uniqueArtists']);
    }

    public function test_stats_for_user_with_no_ratings(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $stats = $response->getData()['stats'];
        $this->assertEquals(0, $stats['totalRatings']);
        $this->assertEquals(0, $stats['averageRating']);
        $this->assertEquals(0, $stats['uniqueArtists']);
    }
}
