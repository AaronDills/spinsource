<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Artist extends Model
{
    use Searchable;
    protected $fillable = [
        'name',
        'sort_name',
        'wikidata_id',
        'musicbrainz_id',
        'description',
        'wikipedia_url',
        'official_website',
        'image_commons',
        'logo_commons',
        'commons_category',
        'formed_year',
        'disbanded_year',
        'country_id',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function genres()
    {
        return $this->belongsToMany(\App\Models\Genre::class, 'artist_genre')->withTimestamps();
    }
    
    public function links()
    {
        return $this->hasMany(\App\Models\ArtistLink::class);
    }
    

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_name' => $this->sort_name,
            'description' => $this->description,
        ];
    }
}
