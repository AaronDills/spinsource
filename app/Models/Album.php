<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    protected $fillable = [
        'title',
        'wikidata_id',
        'musicbrainz_release_group_id',
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
}
