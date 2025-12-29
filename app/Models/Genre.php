<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Genre extends Model
{
    protected $fillable = [
        'name',
        'wikidata_id',
        'musicbrainz_id',
        'description',
        'inception_year',
        'country_id',
        'parent_genre_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Genre::class, 'parent_genre_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Genre::class, 'parent_genre_id');
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class);
    }
}
