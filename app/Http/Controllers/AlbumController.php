<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Services\SeoService;
use Illuminate\View\View;

class AlbumController extends Controller
{
    public function show(Album $album): View
    {
        $album->load('artist.country', 'ratings', 'tracks');

        // Build SEO data
        $seo = $this->buildSeoData($album);

        return view('albums.show', [
            'album' => $album,
            'seo' => $seo,
        ]);
    }

    /**
     * Build SEO metadata for the album page.
     */
    private function buildSeoData(Album $album): array
    {
        $appName = config('app.name', 'Spinsearch');
        $artistName = $album->artist?->name ?? 'Unknown Artist';

        // Build title
        $title = $album->title.' by '.$artistName.' - '.$appName;

        // Build description
        $descriptionParts = [];

        if ($album->album_type) {
            $descriptionParts[] = ucfirst($album->album_type);
        } else {
            $descriptionParts[] = 'Album';
        }

        $descriptionParts[] = 'by '.$artistName;

        if ($album->release_year) {
            $descriptionParts[] = '('.$album->release_year.')';
        }

        $trackCount = $album->tracks->count();
        if ($trackCount > 0) {
            $descriptionParts[] = '- '.$trackCount.' '.($trackCount === 1 ? 'track' : 'tracks');
        }

        $description = $album->description
            ? SeoService::truncateDescription($album->description)
            : SeoService::truncateDescription(
                implode(' ', $descriptionParts).'. View the complete tracklist and album details on '.$appName.'.'
            );

        // Build OG image
        $ogImage = $album->cover_image_url;

        return [
            'title' => $title,
            'description' => $description,
            'ogType' => 'music.album',
            'ogImage' => $ogImage,
            'canonical' => route('album.show', $album),
            'jsonLd' => SeoService::albumJsonLd($album),
        ];
    }
}
