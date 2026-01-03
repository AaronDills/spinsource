<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAlbumRating>
 */
class UserAlbumRatingFactory extends Factory
{
    protected $model = UserAlbumRating::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'album_id' => Album::factory(),
            'rating' => fake()->numberBetween(1, 10),
            'listened_at' => fake()->optional()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forAlbum(Album $album): static
    {
        return $this->state(fn (array $attributes) => [
            'album_id' => $album->id,
        ]);
    }

    public function withRating(int $rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
        ]);
    }
}
