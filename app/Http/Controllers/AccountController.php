<?php

namespace App\Http\Controllers;

use App\Models\UserAlbumRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Get all ratings with pagination
        $ratings = $user->ratings()
            ->with(['album.artist'])
            ->latest()
            ->paginate(15);

        // Get statistics
        $stats = $this->getAccountStats($user);

        // Get listening activity by month (last 12 months)
        $monthlyActivity = $this->getMonthlyActivity($user);

        return view('account.index', [
            'user' => $user,
            'ratings' => $ratings,
            'stats' => $stats,
            'monthlyActivity' => $monthlyActivity,
        ]);
    }

    public function reviews(Request $request): View
    {
        $user = $request->user();

        $sortBy = $request->get('sort', 'recent');
        $filterRating = $request->get('rating');

        $query = $user->ratings()->with(['album.artist']);

        // Apply sorting
        switch ($sortBy) {
            case 'highest':
                $query->orderByDesc('rating');
                break;
            case 'lowest':
                $query->orderBy('rating');
                break;
            case 'album':
                $query->join('albums', 'user_album_ratings.album_id', '=', 'albums.id')
                    ->orderBy('albums.title')
                    ->select('user_album_ratings.*');
                break;
            case 'artist':
                $query->join('albums', 'user_album_ratings.album_id', '=', 'albums.id')
                    ->join('artists', 'albums.artist_id', '=', 'artists.id')
                    ->orderBy('artists.name')
                    ->select('user_album_ratings.*');
                break;
            default:
                $query->latest();
        }

        // Apply rating filter
        if ($filterRating && is_numeric($filterRating)) {
            $query->where('rating', $filterRating);
        }

        $ratings = $query->paginate(20)->withQueryString();

        return view('account.reviews', [
            'user' => $user,
            'ratings' => $ratings,
            'currentSort' => $sortBy,
            'currentRating' => $filterRating,
        ]);
    }

    public function editReview(Request $request, UserAlbumRating $rating): View
    {
        // Ensure the rating belongs to the authenticated user
        if ($rating->user_id !== $request->user()->id) {
            abort(403);
        }

        $rating->load(['album.artist']);

        return view('account.edit-review', [
            'rating' => $rating,
        ]);
    }

    public function updateReview(Request $request, UserAlbumRating $rating): RedirectResponse
    {
        // Ensure the rating belongs to the authenticated user
        if ($rating->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'listened_at' => ['nullable', 'date'],
        ]);

        $rating->update($validated);

        return redirect()
            ->route('account.reviews')
            ->with('status', 'Review updated successfully.');
    }

    public function destroyReview(Request $request, UserAlbumRating $rating): RedirectResponse
    {
        // Ensure the rating belongs to the authenticated user
        if ($rating->user_id !== $request->user()->id) {
            abort(403);
        }

        $rating->delete();

        return redirect()
            ->route('account.reviews')
            ->with('status', 'Review deleted successfully.');
    }

    public function statistics(Request $request): View
    {
        $user = $request->user();

        $stats = $this->getDetailedStats($user);
        $topArtists = $this->getTopArtists($user);
        $ratingDistribution = $this->getRatingDistribution($user);
        $yearlyStats = $this->getYearlyStats($user);

        return view('account.statistics', [
            'user' => $user,
            'stats' => $stats,
            'topArtists' => $topArtists,
            'ratingDistribution' => $ratingDistribution,
            'yearlyStats' => $yearlyStats,
        ]);
    }

    private function getAccountStats($user): array
    {
        $ratings = $user->ratings();

        return [
            'totalRatings' => $ratings->count(),
            'averageRating' => round($ratings->avg('rating') ?? 0, 1),
            'highestRated' => $ratings->max('rating'),
            'lowestRated' => $ratings->min('rating'),
        ];
    }

    private function getDetailedStats($user): array
    {
        $ratings = $user->ratings();

        return [
            'totalRatings' => $ratings->count(),
            'averageRating' => round($ratings->avg('rating') ?? 0, 1),
            'uniqueArtists' => $user->ratings()
                ->join('albums', 'user_album_ratings.album_id', '=', 'albums.id')
                ->distinct('albums.artist_id')
                ->count('albums.artist_id'),
            'ratingsWithNotes' => $user->ratings()->whereNotNull('notes')->count(),
            'ratingsThisMonth' => $user->ratings()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'ratingsThisYear' => $user->ratings()
                ->where('created_at', '>=', now()->startOfYear())
                ->count(),
        ];
    }

    private function getMonthlyActivity($user): array
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push([
                'month' => $date->format('M Y'),
                'count' => $user->ratings()
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ]);
        }

        return $months->toArray();
    }

    private function getTopArtists($user): array
    {
        return $user->ratings()
            ->join('albums', 'user_album_ratings.album_id', '=', 'albums.id')
            ->join('artists', 'albums.artist_id', '=', 'artists.id')
            ->selectRaw('artists.id, artists.name, COUNT(*) as rating_count, AVG(user_album_ratings.rating) as avg_rating')
            ->groupBy('artists.id', 'artists.name')
            ->orderByDesc('rating_count')
            ->limit(10)
            ->get()
            ->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'rating_count' => $artist->rating_count,
                'avg_rating' => round($artist->avg_rating, 1),
            ])
            ->toArray();
    }

    private function getRatingDistribution($user): array
    {
        $distribution = $user->ratings()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill in any missing ratings with 0
        $result = [];
        for ($i = 1; $i <= 10; $i++) {
            $result[$i] = $distribution[$i] ?? 0;
        }

        return $result;
    }

    private function getYearlyStats($user): array
    {
        $driver = \DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite uses strftime for date extraction
            return $user->ratings()
                ->selectRaw("strftime('%Y', created_at) as year, COUNT(*) as count, AVG(rating) as avg_rating")
                ->groupBy('year')
                ->orderBy('year')
                ->get()
                ->map(fn ($stat) => [
                    'year' => (int) $stat->year,
                    'count' => $stat->count,
                    'avg_rating' => round($stat->avg_rating, 1),
                ])
                ->toArray();
        }

        if ($driver === 'pgsql') {
            // PostgreSQL uses DATE_PART for date extraction
            return $user->ratings()
                ->selectRaw("DATE_PART('year', created_at) as year, COUNT(*) as count, AVG(rating) as avg_rating")
                ->groupByRaw("DATE_PART('year', created_at)")
                ->orderBy('year')
                ->get()
                ->map(fn ($stat) => [
                    'year' => (int) $stat->year,
                    'count' => $stat->count,
                    'avg_rating' => round($stat->avg_rating, 1),
                ])
                ->toArray();
        }

        // MySQL uses YEAR function
        return $user->ratings()
            ->selectRaw('YEAR(created_at) as year, COUNT(*) as count, AVG(rating) as avg_rating')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(fn ($stat) => [
                'year' => $stat->year,
                'count' => $stat->count,
                'avg_rating' => round($stat->avg_rating, 1),
            ])
            ->toArray();
    }
}
