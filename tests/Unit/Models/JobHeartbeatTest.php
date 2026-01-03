<?php

namespace Tests\Unit\Models;

use App\Models\JobHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_heartbeat_factory_creates_valid_model(): void
    {
        $heartbeat = JobHeartbeat::factory()->create();

        $this->assertNotNull($heartbeat->id);
        $this->assertNotEmpty($heartbeat->job);
        $this->assertNotEmpty($heartbeat->metric);
        $this->assertNotNull($heartbeat->run_id);
    }

    public function test_for_job_factory_state(): void
    {
        $heartbeat = JobHeartbeat::factory()->forJob('TestJob')->create();

        $this->assertEquals('TestJob', $heartbeat->job);
    }

    public function test_started_factory_state(): void
    {
        $heartbeat = JobHeartbeat::factory()->started()->create();

        $this->assertEquals('started', $heartbeat->metric);
    }

    public function test_completed_factory_state(): void
    {
        $heartbeat = JobHeartbeat::factory()->completed()->create();

        $this->assertEquals('completed', $heartbeat->metric);
    }

    public function test_failed_factory_state(): void
    {
        $heartbeat = JobHeartbeat::factory()->failed('Test error')->create();

        $this->assertEquals('failed', $heartbeat->metric);
        $this->assertEquals('Test error', $heartbeat->context['error']);
    }

    public function test_progress_factory_state(): void
    {
        $heartbeat = JobHeartbeat::factory()->progress(50, 100)->create();

        $this->assertEquals('progress', $heartbeat->metric);
        $this->assertEquals(50, $heartbeat->context['current']);
        $this->assertEquals(100, $heartbeat->context['total']);
        $this->assertEquals(50.0, $heartbeat->context['percent']);
    }

    public function test_with_run_id_factory_state(): void
    {
        $runId = 'test-run-123';
        $heartbeat = JobHeartbeat::factory()->withRunId($runId)->create();

        $this->assertEquals($runId, $heartbeat->run_id);
    }

    public function test_record_static_method(): void
    {
        $heartbeat = JobHeartbeat::record('App\\Jobs\\TestJob', 'custom-metric', ['key' => 'value'], 'run-123');

        $this->assertEquals('TestJob', $heartbeat->job);
        $this->assertEquals('custom-metric', $heartbeat->metric);
        $this->assertEquals(['key' => 'value'], $heartbeat->context);
    }

    public function test_started_static_method(): void
    {
        $heartbeat = JobHeartbeat::started('TestJob', ['batch_size' => 100]);

        $this->assertEquals('TestJob', $heartbeat->job);
        $this->assertEquals('started', $heartbeat->metric);
        $this->assertEquals(100, $heartbeat->context['batch_size']);
    }

    public function test_completed_static_method(): void
    {
        $heartbeat = JobHeartbeat::completed('TestJob', ['processed' => 100]);

        $this->assertEquals('TestJob', $heartbeat->job);
        $this->assertEquals('completed', $heartbeat->metric);
    }

    public function test_failed_static_method(): void
    {
        $heartbeat = JobHeartbeat::failed('TestJob', 'Something broke');

        $this->assertEquals('TestJob', $heartbeat->job);
        $this->assertEquals('failed', $heartbeat->metric);
        $this->assertEquals('Something broke', $heartbeat->context['error']);
    }

    public function test_progress_static_method(): void
    {
        $heartbeat = JobHeartbeat::progress('TestJob', 25, 100);

        $this->assertEquals('TestJob', $heartbeat->job);
        $this->assertEquals('progress', $heartbeat->metric);
        $this->assertEquals(25, $heartbeat->context['current']);
        $this->assertEquals(100, $heartbeat->context['total']);
        $this->assertEquals(25.0, $heartbeat->context['percent']);
    }

    public function test_context_cast(): void
    {
        $heartbeat = JobHeartbeat::factory()->create([
            'context' => ['key' => 'value'],
        ]);

        $this->assertIsArray($heartbeat->context);
        $this->assertEquals('value', $heartbeat->context['key']);
    }

    public function test_recent_method(): void
    {
        JobHeartbeat::factory()->count(5)->create();

        $recent = JobHeartbeat::recent(3);

        $this->assertCount(3, $recent);
        $this->assertArrayHasKey('job', $recent[0]);
        $this->assertArrayHasKey('metric', $recent[0]);
    }

    public function test_prune_method(): void
    {
        JobHeartbeat::factory()->create(['created_at' => now()->subDays(10)]);
        JobHeartbeat::factory()->create(['created_at' => now()->subDays(3)]);
        JobHeartbeat::factory()->create(['created_at' => now()]);

        $deleted = JobHeartbeat::prune(7);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(2, JobHeartbeat::count());
    }
}
