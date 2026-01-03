<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'name' => fake()->country(),
            'wikidata_qid' => 'Q' . fake()->unique()->numberBetween(1, 999999),
            'iso_code' => fake()->countryCode(),
        ];
    }
}
