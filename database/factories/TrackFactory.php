<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        return [
            'album_id' => Album::factory(),
            'musicbrainz_recording_id' => fake()->uuid(),
            'musicbrainz_release_id' => fake()->uuid(),
            'title' => fake()->words(rand(1, 5), true),
            'position' => fake()->numberBetween(1, 20),
            'number' => (string) fake()->numberBetween(1, 20),
            'disc_number' => 1,
            'length_ms' => fake()->numberBetween(60000, 600000),
            'source' => 'musicbrainz',
            'source_last_synced_at' => now(),
        ];
    }

    public function forAlbum(Album $album): static
    {
        return $this->state(fn (array $attributes) => [
            'album_id' => $album->id,
        ]);
    }

    public function atPosition(int $position, int $disc = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
            'number' => (string) $position,
            'disc_number' => $disc,
        ]);
    }

    public function withLength(int $seconds): static
    {
        return $this->state(fn (array $attributes) => [
            'length_ms' => $seconds * 1000,
        ]);
    }
}
