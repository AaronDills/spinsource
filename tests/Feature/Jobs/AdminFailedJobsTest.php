<?php

namespace Tests\Feature\Jobs;

use App\Support\AdminJobManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFailedJobsTest extends TestCase
{
    use RefreshDatabase;

    private AdminJobManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(AdminJobManager::class);
    }

    public function test_failed_jobs_summary_groups_by_exception(): void
    {
        $older = Carbon::now()->subMinutes(5);
        $newer = Carbon::now()->subMinutes(2);
        $latestOther = Carbon::now()->subMinute();

        $firstId = $this->seedFailedJob("First error message\nStack trace line", 'alpha', $older);
        $secondId = $this->seedFailedJob("First error message\r\nAnother trace line", 'beta', $newer);
        $this->seedFailedJob('Different failure message', 'alpha', $latestOther);

        $summary = $this->manager->failedJobsSummary();

        $this->assertTrue($summary['exists']);
        $this->assertEquals(3, $summary['count']);
        $this->assertCount(2, $summary['groups']);

        $grouped = collect($summary['groups'])->keyBy('message');
        $firstGroup = $grouped->get('First error message');
        $this->assertNotNull($firstGroup);
        $this->assertEquals(2, $firstGroup['count']);
        $this->assertEquals($secondId, $firstGroup['example_id']);
        $this->assertEqualsCanonicalizing([
            ['queue' => 'alpha', 'count' => 1],
            ['queue' => 'beta', 'count' => 1],
        ], $firstGroup['queues']);

        $secondGroup = $grouped->get('Different failure message');
        $this->assertNotNull($secondGroup);
        $this->assertEquals(1, $secondGroup['count']);
    }

    public function test_clear_failed_jobs_by_signature_removes_only_matching_rows(): void
    {
        $signature = sha1('Clear me');
        $first = $this->seedFailedJob("Clear me\nStack", 'alpha');
        $second = $this->seedFailedJob("Clear me\nAnother stack", 'beta');
        $keep = $this->seedFailedJob('Leave me alone', 'alpha');

        $result = $this->manager->clearFailedJobs($signature);

        $this->assertTrue($result['ok']);
        $this->assertEquals(2, $result['cleared']);
        $this->assertDatabaseMissing('failed_jobs', ['id' => $first]);
        $this->assertDatabaseMissing('failed_jobs', ['id' => $second]);
        $this->assertDatabaseHas('failed_jobs', ['id' => $keep]);
    }

    public function test_retry_failed_jobs_dispatches_all_ids(): void
    {
        $first = $this->seedFailedJob('Retry all one', 'alpha');
        $second = $this->seedFailedJob('Retry all two', 'beta');
        $ids = collect([$first, $second])->sort()->values();

        Artisan::spy();

        $result = $this->manager->retryFailedJobs();

        $this->assertTrue($result['ok']);
        $this->assertEquals(2, $result['retried']);
        Artisan::shouldHaveReceived('call')
            ->once()
            ->with('queue:retry', ['id' => $ids->implode(',')]);
    }

    public function test_retry_failed_jobs_scoped_by_signature(): void
    {
        $wantedSignature = sha1('Retry me');
        $retryId = $this->seedFailedJob("Retry me\nstack", 'alpha');
        $this->seedFailedJob('Do not retry', 'beta');

        Artisan::spy();

        $result = $this->manager->retryFailedJobs($wantedSignature);

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['retried']);
        Artisan::shouldHaveReceived('call')
            ->once()
            ->with('queue:retry', ['id' => (string) $retryId]);
    }

    private function seedFailedJob(string $message, string $queue, ?Carbon $failedAt = null): int
    {
        return DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ExampleJob']),
            'exception' => $message,
            'failed_at' => ($failedAt ?? Carbon::now())->format('Y-m-d H:i:s'),
        ]);
    }
}
