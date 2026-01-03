<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\AccountController;
use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private AccountController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AccountController;
    }

    public function test_index_returns_view(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/account', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $this->assertEquals('account.index', $response->getName());
    }

    public function test_index_passes_user_data(): void
    {
        $user = User::factory()->create(['name' => 'Account User']);
        $request = Request::create('/account', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $this->assertEquals($user->id, $response->getData()['user']->id);
    }

    public function test_index_calculates_stats(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->count(5)->forUser($user)->withRating(7)->create();

        $request = Request::create('/account', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $stats = $response->getData()['stats'];
        $this->assertEquals(5, $stats['totalRatings']);
        $this->assertEquals(7, $stats['averageRating']);
        $this->assertEquals(7, $stats['highestRated']);
        $this->assertEquals(7, $stats['lowestRated']);
    }

    public function test_index_returns_paginated_ratings(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->count(20)->forUser($user)->create();

        $request = Request::create('/account', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);

        $this->assertEquals(15, $response->getData()['ratings']->perPage());
    }

    public function test_reviews_returns_view(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/account/reviews', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->reviews($request);

        $this->assertEquals('account.reviews', $response->getName());
    }

    public function test_reviews_can_sort_by_highest(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(3)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(10)->create();

        $request = Request::create('/account/reviews', 'GET', ['sort' => 'highest']);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->reviews($request);

        $ratings = $response->getData()['ratings'];
        $this->assertEquals(10, $ratings->first()->rating);
    }

    public function test_reviews_can_sort_by_lowest(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(3)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(10)->create();

        $request = Request::create('/account/reviews', 'GET', ['sort' => 'lowest']);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->reviews($request);

        $ratings = $response->getData()['ratings'];
        $this->assertEquals(3, $ratings->first()->rating);
    }

    public function test_reviews_can_filter_by_rating(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(5)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(8)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(8)->create();

        $request = Request::create('/account/reviews', 'GET', ['rating' => '8']);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->reviews($request);

        $ratings = $response->getData()['ratings'];
        $this->assertCount(2, $ratings);
    }

    public function test_statistics_returns_view(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/account/statistics', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->statistics($request);

        $this->assertEquals('account.statistics', $response->getName());
    }

    public function test_statistics_calculates_detailed_stats(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album)->withRating(8)->create([
            'notes' => 'Great album!',
        ]);

        $request = Request::create('/account/statistics', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->statistics($request);

        $stats = $response->getData()['stats'];
        $this->assertEquals(1, $stats['totalRatings']);
        $this->assertEquals(8.0, $stats['averageRating']);
        $this->assertEquals(1, $stats['uniqueArtists']);
        $this->assertEquals(1, $stats['ratingsWithNotes']);
    }

    public function test_statistics_returns_rating_distribution(): void
    {
        $user = User::factory()->create();
        UserAlbumRating::factory()->forUser($user)->withRating(7)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(7)->create();
        UserAlbumRating::factory()->forUser($user)->withRating(9)->create();

        $request = Request::create('/account/statistics', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->statistics($request);

        $distribution = $response->getData()['ratingDistribution'];
        $this->assertEquals(2, $distribution[7]);
        $this->assertEquals(1, $distribution[9]);
        $this->assertEquals(0, $distribution[5]);
    }

    public function test_statistics_returns_top_artists(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['name' => 'Top Artist']);
        $album1 = Album::factory()->create(['artist_id' => $artist->id]);
        $album2 = Album::factory()->create(['artist_id' => $artist->id]);

        UserAlbumRating::factory()->forUser($user)->forAlbum($album1)->create();
        UserAlbumRating::factory()->forUser($user)->forAlbum($album2)->create();

        $request = Request::create('/account/statistics', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->statistics($request);

        $topArtists = $response->getData()['topArtists'];
        $this->assertCount(1, $topArtists);
        $this->assertEquals('Top Artist', $topArtists[0]['name']);
        $this->assertEquals(2, $topArtists[0]['rating_count']);
    }

    public function test_edit_review_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($otherUser)->create();

        $request = Request::create("/account/reviews/{$rating->id}/edit", 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->controller->editReview($request, $rating);
    }

    public function test_edit_review_returns_view_for_owner(): void
    {
        $user = User::factory()->create();
        $rating = UserAlbumRating::factory()->forUser($user)->create();

        $request = Request::create("/account/reviews/{$rating->id}/edit", 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->editReview($request, $rating);

        $this->assertEquals('account.edit-review', $response->getName());
        $this->assertEquals($rating->id, $response->getData()['rating']->id);
    }
}
