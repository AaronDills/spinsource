<?php

namespace Tests\Unit\Support;

use App\Jobs\WikidataSeedGenres;
use App\Models\JobRun;
use App\Support\AdminJobManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminJobManagerTest extends TestCase
{
    use RefreshDatabase;

    protected AdminJobManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new AdminJobManager();
    }

    /** @test */
    public function definitions_returns_array_of_job_definitions()
    {
        $definitions = $this->manager->definitions();

        $this->assertIsArray($definitions);
        $this->assertNotEmpty($definitions);

        // Check expected job keys exist
        $this->assertArrayHasKey('seed_genres', $definitions);
        $this->assertArrayHasKey('seed_artist_ids', $definitions);
        $this->assertArrayHasKey('musicbrainz_seed_tracklists', $definitions);
    }

    /** @test */
    public function definitions_contain_required_fields()
    {
        $definitions = $this->manager->definitions();

        foreach ($definitions as $key => $definition) {
            $this->assertArrayHasKey('key', $definition);
            $this->assertArrayHasKey('label', $definition);
            $this->assertArrayHasKey('description', $definition);
            $this->assertArrayHasKey('job_class', $definition);
            $this->assertArrayHasKey('queue', $definition);
            $this->assertArrayHasKey('category', $definition);
            $this->assertEquals($key, $definition['key']);
        }
    }

    /** @test */
    public function jobs_with_status_includes_runtime_data()
    {
        $jobs = $this->manager->jobsWithStatus();

        $this->assertIsArray($jobs);
        $this->assertNotEmpty($jobs);

        $firstJob = $jobs[0];
        $this->assertArrayHasKey('job_name', $firstJob);
        $this->assertArrayHasKey('queue_counts', $firstJob);
        $this->assertArrayHasKey('last_run', $firstJob);
        $this->assertArrayHasKey('last_success', $firstJob);
        $this->assertArrayHasKey('running', $firstJob);
    }

    /** @test */
    public function jobs_with_status_sorted_by_category_and_label()
    {
        $jobs = $this->manager->jobsWithStatus();

        // Verify sorted (category|label format)
        for ($i = 0; $i < count($jobs) - 1; $i++) {
            $current = "{$jobs[$i]['category']}|{$jobs[$i]['label']}";
            $next = "{$jobs[$i + 1]['category']}|{$jobs[$i + 1]['label']}";
            $this->assertLessThanOrEqual(0, strcmp($current, $next));
        }
    }

    /** @test */
    public function dispatch_job_returns_error_for_unknown_job_key()
    {
        $result = $this->manager->dispatchJob('invalid_job_key');

        $this->assertFalse($result['dispatched']);
        $this->assertEquals('Unknown job type', $result['message']);
    }

    /** @test */
    public function dispatch_job_dispatches_valid_job()
    {
        Queue::fake();

        $result = $this->manager->dispatchJob('seed_genres');

        $this->assertTrue($result['dispatched']);
        $this->assertStringContainsString('dispatched', $result['message']);

        Queue::assertPushed(WikidataSeedGenres::class);
    }

    /** @test */
    public function dispatch_job_sets_correct_queue()
    {
        Queue::fake();

        $this->manager->dispatchJob('seed_genres');

        Queue::assertPushed(WikidataSeedGenres::class, function ($job) {
            return $job->queue === 'wikidata';
        });
    }

    /** @test */
    public function dispatch_job_with_params_passes_to_constructor()
    {
        Queue::fake();

        $result = $this->manager->dispatchJob('enrich_changed_genres', [
            'genreQids' => ['Q11399', 'Q1344'],
        ]);

        $this->assertTrue($result['dispatched']);
    }

    /** @test */
    public function dispatch_job_fails_with_missing_required_params()
    {
        // Use musicbrainz_fetch_tracklist which requires albumId without default
        $result = $this->manager->dispatchJob('musicbrainz_fetch_tracklist', []);

        $this->assertFalse($result['dispatched']);
        $this->assertStringContainsString('Missing required parameter', $result['message']);
    }

    /** @test */
    public function cancel_job_returns_error_for_unknown_job_key()
    {
        $result = $this->manager->cancelJob('invalid_job_key');

        $this->assertFalse($result['ok']);
        $this->assertEquals('Unknown job type', $result['message']);
    }

    /** @test */
    public function cancel_job_returns_error_for_unsupported_driver()
    {
        // Default test environment uses 'sync' driver which is not supported
        config(['queue.default' => 'sync']);

        $result = $this->manager->cancelJob('seed_genres');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not supported for cancellations', $result['message']);
    }

    /** @test */
    public function cancel_job_marks_running_job_runs_as_failed_with_database_driver()
    {
        // Configure to use database driver
        config([
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
        ]);

        JobRun::factory()->create([
            'job_name' => 'WikidataSeedGenres',
            'status' => JobRun::STATUS_RUNNING,
        ]);

        $result = $this->manager->cancelJob('seed_genres');

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['cancelled_runs']);

        $this->assertDatabaseHas('job_runs', [
            'job_name' => 'WikidataSeedGenres',
            'status' => JobRun::STATUS_FAILED,
            'error_message' => 'Cancelled from admin console',
        ]);
    }

    /** @test */
    public function cancel_job_does_not_affect_completed_jobs_with_database_driver()
    {
        // Configure to use database driver
        config([
            'queue.default' => 'database',
            'queue.connections.database.driver' => 'database',
        ]);

        JobRun::factory()->create([
            'job_name' => 'WikidataSeedGenres',
            'status' => JobRun::STATUS_SUCCESS,
        ]);

        $result = $this->manager->cancelJob('seed_genres');

        $this->assertTrue($result['ok']);
        $this->assertEquals(0, $result['cancelled_runs']);

        $this->assertDatabaseHas('job_runs', [
            'job_name' => 'WikidataSeedGenres',
            'status' => JobRun::STATUS_SUCCESS,
        ]);
    }

    /** @test */
    public function failed_jobs_summary_returns_empty_when_no_failed_jobs()
    {
        $result = $this->manager->failedJobsSummary();

        $this->assertTrue($result['exists']);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['groups']);
    }

    /** @test */
    public function failed_jobs_summary_groups_by_exception_signature()
    {
        // Create 3 failed jobs with same exception
        for ($i = 0; $i < 3; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'sync',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'TestJob']),
                'exception' => "RuntimeException: Database connection failed\nStack trace...",
                'failed_at' => now()->subHours($i),
            ]);
        }

        // Create 1 failed job with different exception
        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => "TimeoutException: API timeout\nStack trace...",
            'failed_at' => now(),
        ]);

        $result = $this->manager->failedJobsSummary();

        $this->assertEquals(4, $result['count']);
        $this->assertCount(2, $result['groups']); // 2 unique exceptions

        // Check first group (3 occurrences)
        $this->assertEquals(3, $result['groups'][0]['count']);
        $this->assertStringContainsString('Database connection failed', $result['groups'][0]['message']);
    }

    /** @test */
    public function failed_jobs_summary_sorts_by_count_desc()
    {
        // 2 of exception A
        for ($i = 0; $i < 2; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'sync',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'TestJob']),
                'exception' => "Exception A\nStack trace...",
                'failed_at' => now(),
            ]);
        }

        // 5 of exception B
        for ($i = 0; $i < 5; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => 'sync',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'TestJob']),
                'exception' => "Exception B\nStack trace...",
                'failed_at' => now(),
            ]);
        }

        $result = $this->manager->failedJobsSummary();

        // Exception B (5) should be first
        $this->assertEquals(5, $result['groups'][0]['count']);
        $this->assertStringContainsString('Exception B', $result['groups'][0]['message']);

        // Exception A (2) should be second
        $this->assertEquals(2, $result['groups'][1]['count']);
        $this->assertStringContainsString('Exception A', $result['groups'][1]['message']);
    }

    /** @test */
    public function failed_jobs_summary_includes_queue_distribution()
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'wikidata',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => "Exception\nStack trace...",
            'failed_at' => now(),
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'musicbrainz',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => "Exception\nStack trace...",
            'failed_at' => now(),
        ]);

        $result = $this->manager->failedJobsSummary();

        $this->assertCount(1, $result['groups']); // Same exception
        $queues = $result['groups'][0]['queues'];

        $this->assertCount(2, $queues);

        $queueNames = array_column($queues, 'queue');
        $this->assertContains('wikidata', $queueNames);
        $this->assertContains('musicbrainz', $queueNames);
    }

    /** @test */
    public function clear_failed_jobs_clears_all_when_no_signature()
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => 'Exception',
            'failed_at' => now(),
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => 'Exception',
            'failed_at' => now(),
        ]);

        $result = $this->manager->clearFailedJobs();

        $this->assertTrue($result['ok']);
        $this->assertEquals(2, $result['cleared']);
        $this->assertEquals(0, DB::table('failed_jobs')->count());
    }

    /** @test */
    public function clear_failed_jobs_clears_by_signature()
    {
        $uuid1 = (string) \Illuminate\Support\Str::uuid();
        $uuid2 = (string) \Illuminate\Support\Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid1,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => "Exception A\nStack trace...",
            'failed_at' => now(),
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid2,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => "Exception B\nStack trace...",
            'failed_at' => now(),
        ]);

        $summary = $this->manager->failedJobsSummary();
        $signatureA = $summary['groups'][0]['signature'];

        $result = $this->manager->clearFailedJobs($signatureA);

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['cleared']);
        $this->assertEquals(1, DB::table('failed_jobs')->count());
    }

    /** @test */
    public function clear_failed_jobs_returns_error_for_invalid_signature()
    {
        $result = $this->manager->clearFailedJobs('invalid_signature_xyz');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No failed jobs found', $result['message']);
    }

    /** @test */
    public function retry_failed_jobs_dispatches_all_when_no_signature()
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', \Mockery::type('array'))
            ->andReturn(0);

        $uuid = (string) \Illuminate\Support\Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'exception' => 'Exception',
            'failed_at' => now(),
        ]);

        $result = $this->manager->retryFailedJobs();

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['retried']);
    }

    /** @test */
    public function retry_failed_jobs_returns_error_when_no_jobs_to_retry()
    {
        $result = $this->manager->retryFailedJobs();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No failed jobs to retry', $result['message']);
    }

    /** @test */
    public function format_duration_formats_seconds()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('formatDuration');
        $method->setAccessible(true);

        $this->assertEquals('30s', $method->invoke($this->manager, 30));
        $this->assertEquals('1m', $method->invoke($this->manager, 60));
        $this->assertEquals('1m 5s', $method->invoke($this->manager, 65));
        $this->assertEquals('10m', $method->invoke($this->manager, 600));
        $this->assertEquals('1h', $method->invoke($this->manager, 3600));
        $this->assertEquals('1h 5m', $method->invoke($this->manager, 3900));
        $this->assertEquals('2h 30m', $method->invoke($this->manager, 9000));
    }

    /** @test */
    public function format_duration_returns_null_for_null()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('formatDuration');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->manager, null));
    }

    /** @test */
    public function format_duration_handles_zero()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('formatDuration');
        $method->setAccessible(true);

        $this->assertEquals('0s', $method->invoke($this->manager, 0));
    }

    /** @test */
    public function normalize_exception_truncates_to_first_line()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('normalizeException');
        $method->setAccessible(true);

        $exception = "RuntimeException: Error occurred\nStack trace:\n  at File.php:123";
        $result = $method->invoke($this->manager, $exception);

        $this->assertEquals('RuntimeException: Error occurred', $result);
    }

    /** @test */
    public function normalize_exception_truncates_long_lines_to_400_chars()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('normalizeException');
        $method->setAccessible(true);

        $exception = str_repeat('a', 500);
        $result = $method->invoke($this->manager, $exception);

        $this->assertEquals(400, mb_strlen($result));
    }

    /** @test */
    public function normalize_exception_returns_default_for_null()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('normalizeException');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager, null);

        $this->assertEquals('Unknown exception', $result);
    }

    /** @test */
    public function normalize_exception_returns_default_for_empty_string()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('normalizeException');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager, '');

        $this->assertEquals('Unknown exception', $result);
    }

    /** @test */
    public function payload_matches_job_matches_by_display_name()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('payloadMatchesJob');
        $method->setAccessible(true);

        $payload = json_encode([
            'displayName' => 'App\\Jobs\\WikidataSeedGenres',
            'data' => [],
        ]);

        $result = $method->invoke($this->manager, $payload, 'App\\Jobs\\WikidataSeedGenres');

        $this->assertTrue($result);
    }

    /** @test */
    public function payload_matches_job_matches_by_command_name()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('payloadMatchesJob');
        $method->setAccessible(true);

        $payload = json_encode([
            'data' => [
                'commandName' => 'App\\Jobs\\WikidataSeedGenres',
            ],
        ]);

        $result = $method->invoke($this->manager, $payload, 'App\\Jobs\\WikidataSeedGenres');

        $this->assertTrue($result);
    }

    /** @test */
    public function payload_matches_job_falls_back_to_string_contains()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('payloadMatchesJob');
        $method->setAccessible(true);

        $payload = 'Some string containing App\\Jobs\\WikidataSeedGenres in it';

        $result = $method->invoke($this->manager, $payload, 'App\\Jobs\\WikidataSeedGenres');

        $this->assertTrue($result);
    }

    /** @test */
    public function payload_matches_job_returns_false_for_non_match()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('payloadMatchesJob');
        $method->setAccessible(true);

        $payload = json_encode([
            'displayName' => 'App\\Jobs\\SomeOtherJob',
        ]);

        $result = $method->invoke($this->manager, $payload, 'App\\Jobs\\WikidataSeedGenres');

        $this->assertFalse($result);
    }

    /** @test */
    public function queue_connection_returns_default_connection()
    {
        config(['queue.default' => 'redis']);

        $result = $this->manager->queueConnection();

        $this->assertEquals('redis', $result);
    }

    /** @test */
    public function queue_driver_returns_driver_for_connection()
    {
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.driver' => 'redis',
        ]);

        $result = $this->manager->queueDriver();

        $this->assertEquals('redis', $result);
    }

    /** @test */
    public function format_run_includes_all_fields_when_run_exists()
    {
        $run = JobRun::factory()->create([
            'job_name' => 'TestJob',
            'status' => JobRun::STATUS_SUCCESS,
            'totals' => ['processed' => 100, 'created' => 50],
            'error_message' => null,
        ]);

        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('formatRun');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager, $run);

        $this->assertEquals($run->id, $result['id']);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('started_at', $result);
        $this->assertArrayHasKey('finished_at', $result);
        $this->assertArrayHasKey('duration_seconds', $result);
        $this->assertArrayHasKey('duration_human', $result);
        $this->assertEquals(['processed' => 100, 'created' => 50], $result['totals']);
    }

    /** @test */
    public function format_run_returns_null_for_null_input()
    {
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('formatRun');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager, null);

        $this->assertNull($result);
    }
}
