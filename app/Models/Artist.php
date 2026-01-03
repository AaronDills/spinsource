<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * Artist entity with canonical external IDs and provenance tracking.
 *
 * ## External ID Mapping
 *
 * | Column                   | Source       | Format                | Example                              |
 * |--------------------------|--------------|----------------------|--------------------------------------|
 * | wikidata_qid             | Wikidata     | Q-ID (string)        | Q2831 (Michael Jackson)              |
 * | musicbrainz_artist_mbid  | MusicBrainz  | UUID (string)        | f27ec8db-af05-4f36-916e-3d57f91ecf5e |
 * | spotify_artist_id        | Spotify      | Base62 ID            | 3fMbdgg4jU18AjLCKBhRSm               |
 * | apple_music_artist_id    | Apple Music  | Numeric ID           | 32940                                |
 * | discogs_artist_id        | Discogs      | Numeric ID           | 15885                                |
 *
 * ## Provenance
 *
 * - `source`: Primary data source ('wikidata', 'musicbrainz')
 * - `source_last_synced_at`: Timestamp of last sync from source
 *
 * @see \App\Jobs\WikidataEnrichArtists - Populates from Wikidata
 */
class Artist extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'name',
        'sort_name',
        'wikidata_qid',
        'musicbrainz_artist_mbid',
        'spotify_artist_id',
        'apple_music_artist_id',
        'discogs_artist_id',
        'description',
        'wikipedia_url',
        'official_website',
        'image_commons',
        'logo_commons',
        'commons_category',
        'formed_year',
        'disbanded_year',
        'country_id',
        'album_count',
        'link_count',
        'artist_type',
        'source',
        'source_last_synced_at',
    ];

    protected $casts = [
        'album_count' => 'integer',
        'link_count' => 'integer',
        'source_last_synced_at' => 'datetime',
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

    /**
     * Whether the artist has a Wikipedia article.
     */
    protected function hasWikipedia(): Attribute
    {
        return Attribute::make(
            get: fn () => ! empty($this->wikipedia_url),
        );
    }

    /**
     * Compute a ranking score for search relevance.
     *
     * Formula:
     *   (has_wikipedia ? 15 : 0)
     * + 6 * ln(album_count + 1)
     * + 2 * ln(link_count + 1)
     * + (spotify_artist_id ? 10 : 0)
     * + (musicbrainz_id ? 3 : 0)
     */
    protected function rankScore(): Attribute
    {
        return Attribute::make(
            get: function () {
                $score = 0;

                // Wikipedia presence is a strong signal
                if ($this->has_wikipedia) {
                    $score += 15;
                }

                // Album count with logarithmic scaling
                $score += 6 * log(($this->album_count ?? 0) + 1);

                // Link count with logarithmic scaling
                $score += 2 * log(($this->link_count ?? 0) + 1);

                // Spotify presence is a strong signal
                if (! empty($this->spotify_artist_id)) {
                    $score += 10;
                }

                // MusicBrainz presence is a moderate signal
                if (! empty($this->musicbrainz_artist_mbid)) {
                    $score += 3;
                }

                return (int) round($score);
            },
        );
    }

    /**
     * Modify the query used to retrieve models when making all searchable.
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->select([
            'id',
            'name',
            'sort_name',
            'wikipedia_url',
            'album_count',
            'link_count',
            'spotify_artist_id',
            'musicbrainz_artist_mbid',
        ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'sort_name' => $this->sort_name,
            'rank_score' => $this->rank_score,
        ];
    }
}
