<?php

namespace Tests\Feature\Jobs;

use App\Models\User;
use App\Support\AdminJobManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminJobsDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_data_gracefully_handles_failed_jobs_errors(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $manager = Mockery::mock(AdminJobManager::class);
        $manager->shouldReceive('queueConnection')->andReturn('redis');
        $manager->shouldReceive('queueDriver')->andReturn('redis');
        $manager->shouldReceive('jobsWithStatus')->andReturn([]);
        $manager->shouldReceive('failedJobsSummary')->andThrow(new \RuntimeException('boom'));

        $this->app->instance(AdminJobManager::class, $manager);

        $response = $this->actingAs($user)->getJson(route('admin.jobs.data'));

        $response->assertOk();
        $response->assertJsonPath('failed_jobs.count', 0);
        $response->assertJsonPath('failed_jobs.groups', []);
        $response->assertJsonPath('failed_jobs.error', 'Failed to load failed jobs');
    }

    public function test_jobs_data_gracefully_handles_jobs_errors(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $manager = Mockery::mock(AdminJobManager::class);
        $manager->shouldReceive('queueConnection')->andReturn('redis');
        $manager->shouldReceive('queueDriver')->andReturn('redis');
        $manager->shouldReceive('jobsWithStatus')->andThrow(new \RuntimeException('jobs blew up'));
        $manager->shouldReceive('failedJobsSummary')->andReturn([
            'exists' => false,
            'count' => 0,
            'groups' => [],
        ]);

        $this->app->instance(AdminJobManager::class, $manager);

        $response = $this->actingAs($user)->getJson(route('admin.jobs.data'));

        $response->assertOk();
        $response->assertJsonPath('jobs', []);
        $response->assertJsonPath('jobs_error', 'Failed to load jobs');
        $response->assertJsonPath('failed_jobs.exists', false);
    }
}
