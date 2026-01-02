<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * Genre entity with canonical external IDs and provenance tracking.
 *
 * ## External ID Mapping
 *
 * | Column         | Source       | Format           | Example                   |
 * |----------------|--------------|-----------------|---------------------------|
 * | wikidata_qid   | Wikidata     | Q-ID (string)   | Q11399 (Rock music)       |
 * | musicbrainz_id | MusicBrainz  | Tag name/ID     | rock                      |
 *
 * ## Provenance
 *
 * - `source`: Primary data source ('wikidata')
 * - `source_last_synced_at`: Timestamp of last sync from source
 *
 * @see \App\Jobs\WikidataSeedGenres - Populates from Wikidata
 */
class Genre extends Model
{
    use Searchable;

    protected $fillable = [
        'name',
        'wikidata_qid',
        'musicbrainz_id',
        'description',
        'inception_year',
        'country_id',
        'parent_genre_id',
        'source',
        'source_last_synced_at',
    ];

    protected $casts = [
        'source_last_synced_at' => 'datetime',
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

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function getScoutKey(): mixed
    {
        return 'genre_'.(string) $this->getKey();
    }
}
