<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Get user statistics
        $stats = $this->getUserStats($user);

        // Get recent ratings with album and artist info
        $recentRatings = $user->recentRatings(10);

        // Get top rated albums by the user
        $topRatedAlbums = $user->ratings()
            ->with(['album.artist'])
            ->orderByDesc('rating')
            ->limit(5)
            ->get();

        // Get rating distribution
        $ratingDistribution = $user->ratings()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return view('dashboard', [
            'user' => $user,
            'stats' => $stats,
            'recentRatings' => $recentRatings,
            'topRatedAlbums' => $topRatedAlbums,
            'ratingDistribution' => $ratingDistribution,
        ]);
    }

    private function getUserStats($user): array
    {
        $ratings = $user->ratings();

        return [
            'totalRatings' => $ratings->count(),
            'averageRating' => round($ratings->avg('rating') ?? 0, 1),
            'uniqueArtists' => $user->ratings()
                ->join('albums', 'user_album_ratings.album_id', '=', 'albums.id')
                ->distinct('albums.artist_id')
                ->count('albums.artist_id'),
            'memberSince' => $user->created_at,
        ];
    }
}
