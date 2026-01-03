<?php

namespace Database\Factories;

use App\Models\DataSourceQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSourceQuery>
 */
class DataSourceQueryFactory extends Factory
{
    protected $model = DataSourceQuery::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'data_source' => 'wikidata',
            'query_type' => 'sparql',
            'query' => 'SELECT ?item WHERE { ?item wdt:P31 wd:Q5 } LIMIT 10',
            'description' => fake()->sentence(),
            'variables' => null,
            'is_active' => true,
        ];
    }

    public function forMusicBrainz(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_source' => 'musicbrainz',
            'query_type' => 'api',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withVariables(array $variables): static
    {
        return $this->state(fn (array $attributes) => [
            'variables' => $variables,
        ]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
