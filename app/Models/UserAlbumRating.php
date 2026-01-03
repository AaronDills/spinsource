<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAlbumRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'album_id',
        'rating',
        'listened_at',
        'notes',
    ];

    protected $casts = [
        'listened_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
