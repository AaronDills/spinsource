<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true) . ' music',
            'wikidata_qid' => 'Q' . fake()->unique()->numberBetween(1, 999999),
            'musicbrainz_id' => fake()->optional()->slug(2),
            'description' => fake()->optional()->sentence(),
            'inception_year' => fake()->optional()->numberBetween(1900, 2020),
            'country_id' => null,
            'parent_genre_id' => null,
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

    public function withParent(?Genre $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_genre_id' => $parent?->id ?? Genre::factory(),
        ]);
    }
}
