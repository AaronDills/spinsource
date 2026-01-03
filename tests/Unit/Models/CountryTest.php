<?php

namespace Tests\Unit\Models;

use App\Models\Artist;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_factory_creates_valid_model(): void
    {
        $country = Country::factory()->create();

        $this->assertNotNull($country->id);
        $this->assertNotEmpty($country->name);
        $this->assertNotEmpty($country->wikidata_qid);
        $this->assertStringStartsWith('Q', $country->wikidata_qid);
    }

    public function test_country_has_many_artists(): void
    {
        $country = Country::factory()->create();
        Artist::factory()->count(3)->create(['country_id' => $country->id]);

        $this->assertCount(3, $country->artists);
        $this->assertInstanceOf(Artist::class, $country->artists->first());
    }

    public function test_country_has_many_genres(): void
    {
        $country = Country::factory()->create();
        Genre::factory()->count(2)->create(['country_id' => $country->id]);

        $this->assertCount(2, $country->genres);
        $this->assertInstanceOf(Genre::class, $country->genres->first());
    }

    public function test_iso_code_is_present(): void
    {
        $country = Country::factory()->create();

        $this->assertNotEmpty($country->iso_code);
    }
}
