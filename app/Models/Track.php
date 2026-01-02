<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Track extends Model
{
    protected $fillable = [
        'album_id',
        'musicbrainz_recording_id',
        'musicbrainz_release_id',
        'title',
        'position',
        'number',
        'disc_number',
        'length_ms',
    ];

    protected $casts = [
        'position' => 'integer',
        'disc_number' => 'integer',
        'length_ms' => 'integer',
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
