<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'provider_name',
        'provider_id',
        'avatar_url',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the user's album ratings.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(UserAlbumRating::class);
    }

    /**
     * Get the user's recent album ratings with album and artist.
     */
    public function recentRatings(int $limit = 5)
    {
        return $this->ratings()
            ->with(['album.artist'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently reviewed artists from the user's ratings.
     */
    public function recentlyReviewedArtists(int $limit = 5)
    {
        return $this->ratings()
            ->with('album.artist')
            ->latest()
            ->get()
            ->pluck('album.artist')
            ->filter()
            ->unique('id')
            ->take($limit);
    }

    /**
     * Get recently reviewed albums from the user's ratings.
     */
    public function recentlyReviewedAlbums(int $limit = 5)
    {
        return $this->ratings()
            ->with('album.artist')
            ->latest()
            ->limit($limit)
            ->get()
            ->pluck('album')
            ->filter();
    }
}
