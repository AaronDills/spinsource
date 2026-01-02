<?php

namespace App\Http\Controllers;

use App\Models\Album;
use Illuminate\View\View;

class AlbumController extends Controller
{
    public function show(Album $album): View
    {
        $album->load('artist.country', 'ratings', 'tracks');

        return view('albums.show', [
            'album' => $album,
        ]);
    }
}
