<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Track entity with MusicBrainz identifiers and provenance tracking.
 *
 * ## External ID Mapping
 *
 * | Column                  | Source       | Description                           |
 * |-------------------------|--------------|---------------------------------------|
 * | musicbrainz_recording_id| MusicBrainz  | Recording MBID (the performance)      |
 * | musicbrainz_release_id  | MusicBrainz  | Release MBID this track came from     |
 *
 * ## MusicBrainz Track vs Recording
 *
 * - Recording: A unique performance (same across all releases)
 * - Track: Position on a specific release (may vary by edition)
 * - We store both to enable cross-referencing
 *
 * ## Provenance
 *
 * - `source`: Data source ('musicbrainz')
 * - `source_last_synced_at`: Timestamp of last sync from source
 *
 * @see \App\Jobs\MusicBrainzFetchTracklist - Populates tracks from MusicBrainz
 */
class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'album_id',
        'musicbrainz_recording_id',
        'musicbrainz_release_id',
        'title',
        'position',
        'number',
        'disc_number',
        'length_ms',
        'source',
        'source_last_synced_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'disc_number' => 'integer',
        'length_ms' => 'integer',
        'source_last_synced_at' => 'datetime',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Get formatted duration (M:SS or H:MM:SS for long tracks).
     */
    public function getFormattedLengthAttribute(): ?string
    {
        if (! $this->length_ms) {
            return null;
        }

        $totalSeconds = (int) ($this->length_ms / 1000);
        $hours = (int) ($totalSeconds / 3600);
        $minutes = (int) (($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
