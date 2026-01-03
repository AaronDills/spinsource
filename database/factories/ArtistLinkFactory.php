<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\ArtistLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtistLink>
 */
class ArtistLinkFactory extends Factory
{
    protected $model = ArtistLink::class;

    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'type' => fake()->randomElement(['spotify', 'apple_music', 'youtube', 'twitter', 'instagram', 'facebook', 'bandcamp', 'soundcloud']),
            'url' => fake()->url(),
            'source' => 'wikidata',
            'is_official' => fake()->boolean(70),
        ];
    }

    public function forArtist(Artist $artist): static
    {
        return $this->state(fn (array $attributes) => [
            'artist_id' => $artist->id,
        ]);
    }

    public function official(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_official' => true,
        ]);
    }

    public function spotify(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'spotify',
            'url' => 'https://open.spotify.com/artist/' . fake()->regexify('[a-zA-Z0-9]{22}'),
        ]);
    }
}
