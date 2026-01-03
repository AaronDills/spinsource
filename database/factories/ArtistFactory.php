<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'sort_name' => $name,
            'wikidata_qid' => 'Q' . fake()->unique()->numberBetween(1, 999999),
            'musicbrainz_artist_mbid' => fake()->optional()->uuid(),
            'spotify_artist_id' => fake()->optional()->regexify('[a-zA-Z0-9]{22}'),
            'apple_music_artist_id' => fake()->optional()->numerify('##########'),
            'description' => fake()->optional()->paragraph(),
            'wikipedia_url' => fake()->optional()->url(),
            'official_website' => fake()->optional()->url(),
            'image_commons' => null,
            'logo_commons' => null,
            'commons_category' => null,
            'formed_year' => fake()->optional()->numberBetween(1950, 2020),
            'disbanded_year' => null,
            'country_id' => null,
            'album_count' => 0,
            'link_count' => 0,
            'artist_type' => fake()->randomElement(['person', 'group', 'orchestra', 'choir']),
            'source' => 'wikidata',
            'source_last_synced_at' => now(),
        ];
    }

    public function withCountry(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => Country::factory(),
        ]);
    }

    public function disbanded(): static
    {
        return $this->state(fn (array $attributes) => [
            'disbanded_year' => fake()->numberBetween(
                $attributes['formed_year'] ?? 1950,
                2024
            ),
        ]);
    }
}
