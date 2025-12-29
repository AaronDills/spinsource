<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistLink extends Model
{
    protected $fillable = [
        'artist_id',
        'type',
        'url',
        'source',
        'is_official',
    ];

    protected $casts = [
        'is_official' => 'boolean',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
