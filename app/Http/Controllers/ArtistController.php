<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use Illuminate\View\View;

class ArtistController extends Controller
{
    public function show(Artist $artist): View
    {
        $artist->load('country', 'genres', 'links', 'albums');

        return view('artists.show', [
            'artist' => $artist,
        ]);
    }
}
