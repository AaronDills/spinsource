<?php

namespace Database\Factories;

use App\Models\JobRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobRun>
 */
class JobRunFactory extends Factory
{
    protected $model = JobRun::class;

    public function definition(): array
    {
        return [
            'job_name' => fake()->randomElement([
                'WikidataSeedArtists',
                'WikidataSeedAlbums',
                'WikidataSeedGenres',
                'MusicBrainzFetchTracklist',
                'WikidataEnrichArtists',
            ]),
            'started_at' => now(),
            'finished_at' => null,
            'status' => JobRun::STATUS_RUNNING,
            'totals' => [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'api_calls' => 0,
            ],
            'last_cursor' => null,
            'error_message' => null,
        ];
    }

    public function forJob(string $jobName): static
    {
        return $this->state(fn (array $attributes) => [
            'job_name' => $jobName,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobRun::STATUS_RUNNING,
            'finished_at' => null,
        ]);
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobRun::STATUS_SUCCESS,
            'finished_at' => now(),
        ]);
    }

    public function failed(string $error = 'Test error'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobRun::STATUS_FAILED,
            'finished_at' => now(),
            'error_message' => $error,
        ]);
    }

    public function withTotals(array $totals): static
    {
        return $this->state(fn (array $attributes) => [
            'totals' => array_merge($attributes['totals'] ?? [], $totals),
        ]);
    }

    public function withCursor(string $cursor): static
    {
        return $this->state(fn (array $attributes) => [
            'last_cursor' => $cursor,
        ]);
    }
}
