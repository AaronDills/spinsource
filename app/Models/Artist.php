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
        'quality_score',
        'artist_type',
        'source',
        'source_last_synced_at',
    ];

    protected $casts = [
        'album_count' => 'integer',
        'link_count' => 'integer',
        'quality_score' => 'integer',
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
     * Compute quality score for search ranking.
     *
     * This is a static method so it can be used both for the Attribute
     * and for batch computation in ArtistsRecomputeMetrics.
     *
     * Formula (0-100 scale):
     * - Wikipedia presence: +15 (strong notability signal)
     * - Description presence: +5
     * - Image presence: +5
     * - Official website: +3
     * - External IDs: Spotify (+10), Apple Music (+5), MusicBrainz (+3), Discogs (+2)
     * - Album count: 6 * ln(album_count + 1), max ~25
     * - Link count: 2 * ln(link_count + 1), max ~10
     */
    public static function computeQualityScore(array $data): int
    {
        $score = 0;

        // Wikipedia presence is a strong notability signal
        if (! empty($data['wikipedia_url'])) {
            $score += 15;
        }

        // Description presence
        if (! empty($data['description'])) {
            $score += 5;
        }

        // Image presence
        if (! empty($data['image_commons'])) {
            $score += 5;
        }

        // Official website
        if (! empty($data['official_website'])) {
            $score += 3;
        }

        // External IDs (weighted by platform importance)
        if (! empty($data['spotify_artist_id'])) {
            $score += 10;
        }
        if (! empty($data['apple_music_artist_id'])) {
            $score += 5;
        }
        if (! empty($data['musicbrainz_artist_mbid'])) {
            $score += 3;
        }
        if (! empty($data['discogs_artist_id'])) {
            $score += 2;
        }

        // Album count with logarithmic scaling (diminishing returns)
        $albumCount = $data['album_count'] ?? 0;
        $score += min(25, 6 * log($albumCount + 1));

        // Link count with logarithmic scaling
        $linkCount = $data['link_count'] ?? 0;
        $score += min(10, 2 * log($linkCount + 1));

        // Cap at 100
        return (int) min(100, round($score));
    }

    /**
     * Get the quality score, preferring stored value, falling back to computed.
     */
    protected function rankScore(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quality_score ?? self::computeQualityScore($this->toArray()),
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
            'quality_score',
        ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'sort_name' => $this->sort_name,
            'rank_score' => $this->quality_score ?? 0,
        ];
    }
}
