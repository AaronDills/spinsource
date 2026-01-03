<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    protected $model = Album::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(rand(1, 4), true),
            'wikidata_qid' => 'Q' . fake()->unique()->numberBetween(1, 999999),
            'musicbrainz_release_group_mbid' => fake()->optional()->uuid(),
            'musicbrainz_release_mbid' => null,
            'selected_release_mbid' => null,
            'spotify_album_id' => fake()->optional()->regexify('[a-zA-Z0-9]{22}'),
            'apple_music_album_id' => fake()->optional()->numerify('##########'),
            'artist_id' => Artist::factory(),
            'album_type' => fake()->randomElement(['studio album', 'live album', 'compilation', 'EP', 'single']),
            'release_year' => fake()->optional()->numberBetween(1950, 2024),
            'release_date' => fake()->optional()->date(),
            'description' => fake()->optional()->paragraph(),
            'wikipedia_url' => fake()->optional()->url(),
            'cover_image_commons' => null,
            'source' => 'wikidata',
            'source_last_synced_at' => now(),
            'tracklist_attempted_at' => null,
            'tracklist_fetched_at' => null,
            'tracklist_fetch_attempts' => 0,
        ];
    }

    public function withArtist(?Artist $artist = null): static
    {
        return $this->state(fn (array $attributes) => [
            'artist_id' => $artist?->id ?? Artist::factory(),
        ]);
    }

    public function withMusicBrainz(): static
    {
        return $this->state(fn (array $attributes) => [
            'musicbrainz_release_group_mbid' => fake()->uuid(),
            'musicbrainz_release_mbid' => fake()->uuid(),
            'selected_release_mbid' => fake()->uuid(),
        ]);
    }

    public function withTracklist(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracklist_fetched_at' => now(),
            'tracklist_fetch_attempts' => 1,
        ]);
    }

    public function needsTracklist(): static
    {
        return $this->state(fn (array $attributes) => [
            'musicbrainz_release_group_mbid' => fake()->uuid(),
            'tracklist_fetched_at' => null,
            'tracklist_attempted_at' => null,
        ]);
    }
}
