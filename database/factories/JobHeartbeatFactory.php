<?php

namespace Database\Factories;

use App\Models\JobHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobHeartbeat>
 */
class JobHeartbeatFactory extends Factory
{
    protected $model = JobHeartbeat::class;

    public function definition(): array
    {
        return [
            'job' => fake()->randomElement([
                'WikidataSeedArtists',
                'WikidataSeedAlbums',
                'MusicBrainzFetchTracklist',
            ]),
            'metric' => fake()->randomElement(['started', 'progress', 'completed', 'failed']),
            'context' => [],
            'run_id' => Str::uuid()->toString(),
        ];
    }

    public function forJob(string $jobName): static
    {
        return $this->state(fn (array $attributes) => [
            'job' => $jobName,
        ]);
    }

    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric' => 'started',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric' => 'completed',
        ]);
    }

    public function failed(string $error = 'Test error'): static
    {
        return $this->state(fn (array $attributes) => [
            'metric' => 'failed',
            'context' => ['error' => $error],
        ]);
    }

    public function progress(int $current, int $total): static
    {
        return $this->state(fn (array $attributes) => [
            'metric' => 'progress',
            'context' => [
                'current' => $current,
                'total' => $total,
                'percent' => $total > 0 ? round(($current / $total) * 100, 1) : 0,
            ],
        ]);
    }

    public function withRunId(string $runId): static
    {
        return $this->state(fn (array $attributes) => [
            'run_id' => $runId,
        ]);
    }
}
