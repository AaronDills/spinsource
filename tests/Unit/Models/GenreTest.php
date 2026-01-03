<?php

namespace Tests\Unit\Models;

use App\Models\Artist;
use App\Models\Country;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_factory_creates_valid_model(): void
    {
        $genre = Genre::factory()->create();

        $this->assertNotNull($genre->id);
        $this->assertNotEmpty($genre->name);
        $this->assertNotEmpty($genre->wikidata_qid);
        $this->assertStringStartsWith('Q', $genre->wikidata_qid);
    }

    public function test_genre_belongs_to_country(): void
    {
        $country = Country::factory()->create();
        $genre = Genre::factory()->create(['country_id' => $country->id]);

        $this->assertInstanceOf(Country::class, $genre->country);
        $this->assertEquals($country->id, $genre->country->id);
    }

    public function test_genre_belongs_to_parent(): void
    {
        $parent = Genre::factory()->create();
        $child = Genre::factory()->create(['parent_genre_id' => $parent->id]);

        $this->assertInstanceOf(Genre::class, $child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_genre_has_many_children(): void
    {
        $parent = Genre::factory()->create();
        Genre::factory()->count(3)->create(['parent_genre_id' => $parent->id]);

        $this->assertCount(3, $parent->children);
        $this->assertInstanceOf(Genre::class, $parent->children->first());
    }

    public function test_genre_belongs_to_many_artists(): void
    {
        $genre = Genre::factory()->create();
        $artists = Artist::factory()->count(2)->create();

        $genre->artists()->attach($artists->pluck('id'));

        $this->assertCount(2, $genre->artists);
        $this->assertInstanceOf(Artist::class, $genre->artists->first());
    }

    public function test_with_country_factory_state(): void
    {
        $genre = Genre::factory()->withCountry()->create();

        $this->assertNotNull($genre->country_id);
        $this->assertInstanceOf(Country::class, $genre->country);
    }

    public function test_with_parent_factory_state(): void
    {
        $genre = Genre::factory()->withParent()->create();

        $this->assertNotNull($genre->parent_genre_id);
        $this->assertInstanceOf(Genre::class, $genre->parent);
    }

    public function test_searchable_array_structure(): void
    {
        $genre = Genre::factory()->create([
            'name' => 'Test Genre',
            'description' => 'A test genre description',
        ]);

        $searchable = $genre->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertArrayHasKey('name', $searchable);
        $this->assertArrayHasKey('description', $searchable);
        $this->assertEquals('Test Genre', $searchable['name']);
    }
}
