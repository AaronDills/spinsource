<?php

namespace App\Http\Controllers;

use App\Enums\AlbumType;
use App\Models\Artist;
use App\Services\SeoService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ArtistController extends Controller
{
    public function show(Artist $artist): View
    {
        $artist->load('country', 'genres', 'links', 'albums');

        // Group albums by type in display order
        $albumsByType = $this->groupAlbumsByType($artist->albums);

        // Deduplicate links - one per type, preferring official and regional variants
        $deduplicatedLinks = $this->deduplicateLinks($artist->links);

        // Build SEO data
        $seo = $this->buildSeoData($artist);

        return view('artists.show', [
            'artist' => $artist,
            'albumsByType' => $albumsByType,
            'deduplicatedLinks' => $deduplicatedLinks,
            'seo' => $seo,
        ]);
    }

    /**
     * Build SEO metadata for the artist page.
     */
    private function buildSeoData(Artist $artist): array
    {
        $appName = config('app.name', 'Spinsearch');

        // Build title
        $title = $artist->name.' - '.$appName;

        // Build description
        $descriptionParts = [];

        if ($artist->genres->isNotEmpty()) {
            $descriptionParts[] = $artist->genres->pluck('name')->take(3)->join(', ').' artist';
        } else {
            $descriptionParts[] = 'Artist';
        }

        if ($artist->country) {
            $descriptionParts[] = 'from '.$artist->country->name;
        }

        if ($artist->formed_year) {
            $descriptionParts[] = 'formed in '.$artist->formed_year;
        }

        $albumCount = $artist->albums->count();
        if ($albumCount > 0) {
            $descriptionParts[] = 'with '.$albumCount.' '.($albumCount === 1 ? 'release' : 'releases');
        }

        $description = $artist->description
            ? SeoService::truncateDescription($artist->description)
            : SeoService::truncateDescription(
                $artist->name.': '.implode(' ', $descriptionParts).'. Explore the complete discography on '.$appName.'.'
            );

        // Build OG image
        $ogImage = null;
        if ($artist->image_commons) {
            $ogImage = 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($artist->image_commons).'?width=600';
        }

        return [
            'title' => $title,
            'description' => $description,
            'ogType' => 'music.musician',
            'ogImage' => $ogImage,
            'canonical' => route('artist.show', $artist),
            'jsonLd' => SeoService::artistJsonLd($artist),
        ];
    }

    /**
     * Group albums by type in a user-friendly display order.
     */
    private function groupAlbumsByType(Collection $albums): array
    {
        // Define display order and labels
        $typeOrder = [
            AlbumType::ALBUM->value => 'Albums',
            AlbumType::EP->value => 'EPs',
            AlbumType::SINGLE->value => 'Singles',
            AlbumType::LIVE->value => 'Live Albums',
            AlbumType::COMPILATION->value => 'Compilations',
            AlbumType::SOUNDTRACK->value => 'Soundtracks',
            AlbumType::REMIX->value => 'Remix Albums',
            AlbumType::OTHER->value => 'Other Releases',
        ];

        $grouped = [];
        foreach ($typeOrder as $type => $label) {
            $typeAlbums = $albums
                ->where('album_type', $type)
                ->sortByDesc(function ($album) {
                    // Sort by release_date if available, otherwise release_year
                    // Nulls sort to the end (use 0 as fallback)
                    if ($album->release_date) {
                        return $album->release_date->timestamp;
                    }

                    return $album->release_year ?? 0;
                })
                ->values();

            if ($typeAlbums->isNotEmpty()) {
                $grouped[] = [
                    'type' => $type,
                    'label' => $label,
                    'albums' => $typeAlbums,
                ];
            }
        }

        return $grouped;
    }

    /**
     * Deduplicate links - keep one per type with smart selection.
     *
     * Priority order:
     * 1. Official links (is_official = true)
     * 2. For Apple Music: prefer /us/ regional URLs
     * 3. For social platforms: prefer verified/canonical URLs
     */
    private function deduplicateLinks(Collection $links): Collection
    {
        return $links
            ->groupBy('type')
            ->map(function (Collection $typeLinks, string $type) {
                // Sort by priority: official first, then apply type-specific rules
                return $typeLinks
                    ->sortByDesc(function ($link) use ($type) {
                        $score = 0;

                        // Heavily prefer official links
                        if ($link->is_official) {
                            $score += 1000;
                        }

                        // Apple Music: prefer /us/ URLs
                        if ($type === 'apple_music') {
                            if (str_contains($link->url, '/us/')) {
                                $score += 100;
                            }
                            // Deprioritize geo-specific URLs that aren't US
                            if (preg_match('#/[a-z]{2}/#', $link->url) && ! str_contains($link->url, '/us/')) {
                                $score -= 50;
                            }
                        }

                        // Prefer shorter/cleaner URLs (likely canonical)
                        $score -= strlen($link->url) / 100;

                        return $score;
                    })
                    ->first();
            })
            ->filter()
            ->values();
    }
}
