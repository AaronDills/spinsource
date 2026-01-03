<?php

namespace Database\Factories;

use App\Models\IngestionCheckpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestionCheckpoint>
 */
class IngestionCheckpointFactory extends Factory
{
    protected $model = IngestionCheckpoint::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'last_seen_oid' => null,
            'last_changed_at' => null,
            'meta' => null,
        ];
    }

    public function forKey(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }

    public function withOid(int $oid): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_oid' => $oid,
        ]);
    }

    public function withChangedAt(\DateTimeInterface $timestamp): static
    {
        return $this->state(fn (array $attributes) => [
            'last_changed_at' => $timestamp,
        ]);
    }

    public function withMeta(array $meta): static
    {
        return $this->state(fn (array $attributes) => [
            'meta' => $meta,
        ]);
    }
}
