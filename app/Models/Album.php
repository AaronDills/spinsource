<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * Album entity with canonical external IDs and provenance tracking.
 *
 * ## External ID Mapping
 *
 * | Column                       | Source       | Description                                    |
 * |------------------------------|--------------|------------------------------------------------|
 * | wikidata_qid                 | Wikidata     | Q-ID for the album entity                      |
 * | musicbrainz_release_group_mbid| MusicBrainz | Release group MBID (groups all editions)       |
 * | musicbrainz_release_mbid     | MusicBrainz  | Canonical release MBID (specific edition)      |
 * | selected_release_mbid        | MusicBrainz  | Release MBID used for tracklist import         |
 * | spotify_album_id             | Spotify      | Base62 album ID                                |
 * | apple_music_album_id         | Apple Music  | Numeric album ID                               |
 *
 * ## MusicBrainz Release Hierarchy
 *
 * MusicBrainz uses a hierarchy: Release Group → Release → Track
 * - Release Group: "Abbey Road" (the conceptual album)
 * - Release: "Abbey Road (2019 Remaster, US CD)" (a specific edition)
 * - selected_release_mbid: Which release we fetched tracks from
 *
 * ## Provenance
 *
 * - `source`: Primary data source ('wikidata', 'musicbrainz')
 * - `source_last_synced_at`: Timestamp of last sync from source
 *
 * @see \App\Jobs\WikidataSeedAlbums - Populates from Wikidata
 * @see \App\Jobs\MusicBrainzFetchTracklist - Populates MBIDs and tracks
 */
class Album extends Model
{
    use Searchable;

    protected $fillable = [
        'title',
        'wikidata_qid',
        'musicbrainz_release_group_mbid',
        'musicbrainz_release_mbid',
        'selected_release_mbid',
        'spotify_album_id',
        'apple_music_album_id',
        'artist_id',
        'album_type',
        'release_year',
        'release_date',
        'description',
        'wikipedia_url',
        'cover_image_commons',
        'source',
        'source_last_synced_at',
    ];

    protected $casts = [
        'release_date' => 'date',
        'source_last_synced_at' => 'datetime',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    // Future expansion point (MusicBrainz: releases/editions)
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)->orderBy('disc_number')->orderBy('position');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(UserAlbumRating::class);
    }

    /**
     * Get the full Wikimedia Commons URL for the cover image.
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cover_image_commons
                ? 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($this->cover_image_commons).'?width=300'
                : null,
        );
    }

    /**
     * Modify the query used to retrieve models when making all searchable.
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query
            ->select(['id', 'title', 'release_year', 'artist_id'])
            ->with('artist:id,name');
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'release_year' => $this->release_year,
            'artist_name' => $this->relationLoaded('artist')
                ? $this->artist?->name
                : $this->artist()->value('name'),
        ];
    }
}
