<?php

namespace Tests\Unit\Models;

use App\Models\JobRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_run_factory_creates_valid_model(): void
    {
        $run = JobRun::factory()->create();

        $this->assertNotNull($run->id);
        $this->assertNotEmpty($run->job_name);
        $this->assertNotNull($run->started_at);
        $this->assertEquals(JobRun::STATUS_RUNNING, $run->status);
    }

    public function test_for_job_factory_state(): void
    {
        $run = JobRun::factory()->forJob('TestJob')->create();

        $this->assertEquals('TestJob', $run->job_name);
    }

    public function test_running_factory_state(): void
    {
        $run = JobRun::factory()->running()->create();

        $this->assertEquals(JobRun::STATUS_RUNNING, $run->status);
        $this->assertNull($run->finished_at);
    }

    public function test_successful_factory_state(): void
    {
        $run = JobRun::factory()->successful()->create();

        $this->assertEquals(JobRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->finished_at);
    }

    public function test_failed_factory_state(): void
    {
        $run = JobRun::factory()->failed('Custom error')->create();

        $this->assertEquals(JobRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertEquals('Custom error', $run->error_message);
    }

    public function test_with_totals_factory_state(): void
    {
        $run = JobRun::factory()->withTotals(['processed' => 100, 'created' => 50])->create();

        $this->assertEquals(100, $run->totals['processed']);
        $this->assertEquals(50, $run->totals['created']);
    }

    public function test_with_cursor_factory_state(): void
    {
        $run = JobRun::factory()->withCursor('cursor-abc')->create();

        $this->assertEquals('cursor-abc', $run->last_cursor);
    }

    public function test_start_run_static_method(): void
    {
        $run = JobRun::startRun('TestJob', 'initial-cursor');

        $this->assertEquals('TestJob', $run->job_name);
        $this->assertEquals('initial-cursor', $run->last_cursor);
        $this->assertEquals(JobRun::STATUS_RUNNING, $run->status);
        $this->assertEquals(0, $run->getTotal('processed'));
    }

    public function test_increment_total_method(): void
    {
        $run = JobRun::factory()->create();

        $run->incrementTotal('processed', 5);
        $run->incrementTotal('created');

        $this->assertEquals(5, $run->getTotal('processed'));
        $this->assertEquals(1, $run->getTotal('created'));
    }

    public function test_set_totals_method(): void
    {
        $run = JobRun::factory()->create();

        $run->setTotals(['processed' => 10, 'created' => 5]);

        $this->assertEquals(10, $run->getTotal('processed'));
        $this->assertEquals(5, $run->getTotal('created'));
    }

    public function test_set_cursor_method(): void
    {
        $run = JobRun::factory()->create();

        $run->setCursor('new-cursor');

        $this->assertEquals('new-cursor', $run->fresh()->last_cursor);
    }

    public function test_success_method(): void
    {
        $run = JobRun::factory()->running()->create();

        $run->success('final-cursor');

        $this->assertEquals(JobRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertEquals('final-cursor', $run->last_cursor);
    }

    public function test_fail_method(): void
    {
        $run = JobRun::factory()->running()->create();

        $run->fail('Something went wrong');

        $this->assertEquals(JobRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertEquals('Something went wrong', $run->error_message);
    }

    public function test_last_successful_static_method(): void
    {
        JobRun::factory()->forJob('TestJob')->failed()->create();
        $successful = JobRun::factory()->forJob('TestJob')->successful()->create();

        $result = JobRun::lastSuccessful('TestJob');

        $this->assertEquals($successful->id, $result->id);
    }

    public function test_last_run_static_method(): void
    {
        JobRun::factory()->forJob('TestJob')->successful()->create(['started_at' => now()->subMinute()]);
        $latest = JobRun::factory()->forJob('TestJob')->running()->create(['started_at' => now()]);

        $result = JobRun::lastRun('TestJob');

        $this->assertEquals($latest->id, $result->id);
    }

    public function test_last_cursor_static_method(): void
    {
        JobRun::factory()->forJob('TestJob')->withCursor('old-cursor')->create(['started_at' => now()->subMinute()]);
        JobRun::factory()->forJob('TestJob')->withCursor('new-cursor')->create(['started_at' => now()]);

        $result = JobRun::lastCursor('TestJob');

        $this->assertEquals('new-cursor', $result);
    }

    public function test_is_running_static_method(): void
    {
        $this->assertFalse(JobRun::isRunning('TestJob'));

        JobRun::factory()->forJob('TestJob')->running()->create();

        $this->assertTrue(JobRun::isRunning('TestJob'));
    }

    public function test_summary_method(): void
    {
        $run = JobRun::factory()->withTotals([
            'processed' => 100,
            'created' => 50,
            'errors' => 2,
        ])->create();

        $summary = $run->summary();

        $this->assertStringContainsString('processed=100', $summary);
        $this->assertStringContainsString('created=50', $summary);
        $this->assertStringContainsString('errors=2', $summary);
    }

    public function test_stale_runs_static_method(): void
    {
        JobRun::factory()->running()->create(['started_at' => now()->subHours(2)]);
        JobRun::factory()->running()->create(['started_at' => now()->subMinutes(30)]);

        $stale = JobRun::staleRuns(60);

        $this->assertCount(1, $stale);
    }
}
