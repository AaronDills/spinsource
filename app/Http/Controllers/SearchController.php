<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Search artists
        $artists = Artist::search($query)
            ->take(5)
            ->get()
            ->load('country', 'genres');

        foreach ($artists as $artist) {
            $subtext = $this->buildArtistSubtext($artist);
            $results[] = [
                'id' => $artist->id,
                'type' => 'artist',
                'title' => $artist->name,
                'subtext' => $subtext,
            ];
        }

        // Search albums
        $albums = Album::search($query)
            ->take(5)
            ->get()
            ->load('artist');

        foreach ($albums as $album) {
            $subtext = $this->buildAlbumSubtext($album);
            $results[] = [
                'id' => $album->id,
                'type' => 'album',
                'title' => $album->title,
                'subtext' => $subtext,
            ];
        }

        return response()->json($results);
    }

    private function buildArtistSubtext(Artist $artist): string
    {
        $parts = ['Artist'];

        $genre = $artist->genres->first();
        if ($genre) {
            $parts[] = $genre->name;
        }

        if ($artist->country) {
            $parts[] = $artist->country->name;
        }

        return implode(' · ', $parts);
    }

    private function buildAlbumSubtext(Album $album): string
    {
        $parts = ['Album'];

        if ($album->artist) {
            $parts[] = $album->artist->name;
        }

        if ($album->release_year) {
            $parts[] = (string) $album->release_year;
        }

        return implode(' · ', $parts);
    }
}
