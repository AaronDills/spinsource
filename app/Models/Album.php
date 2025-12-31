<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Album extends Model
{
    use Searchable;

    protected $fillable = [
        'title',
        'wikidata_id',
        'musicbrainz_release_group_id',
        'spotify_album_id',
        'apple_music_album_id',
        'artist_id',
        'album_type',
        'release_year',
        'release_date',
        'description',
        'wikipedia_url',
    ];

    protected $casts = [
        'release_date' => 'date',
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

    public function ratings(): HasMany
    {
        return $this->hasMany(UserAlbumRating::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'release_year' => $this->release_year,
            'artist_name' => $this->artist?->name,
        ];
    }
}
