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

        [$firstId, $firstUuid] = $this->seedFailedJob("First error message\nStack trace line", 'alpha', $older);
        [$secondId, $secondUuid] = $this->seedFailedJob("First error message\r\nAnother trace line", 'beta', $newer);
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
        [$firstId, $firstUuid] = $this->seedFailedJob("Clear me\nStack", 'alpha');
        [$secondId, $secondUuid] = $this->seedFailedJob("Clear me\nAnother stack", 'beta');
        [$keepId, $keepUuid] = $this->seedFailedJob('Leave me alone', 'alpha');

        $result = $this->manager->clearFailedJobs($signature);

        $this->assertTrue($result['ok']);
        $this->assertEquals(2, $result['cleared']);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $firstUuid]);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $secondUuid]);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $keepUuid]);
    }

    public function test_retry_failed_jobs_dispatches_all_ids(): void
    {
        [$firstId, $firstUuid] = $this->seedFailedJob('Retry all one', 'alpha');
        [$secondId, $secondUuid] = $this->seedFailedJob('Retry all two', 'beta');
        $expectedUuids = [$firstUuid, $secondUuid];

        Artisan::spy();

        $result = $this->manager->retryFailedJobs();

        $this->assertTrue($result['ok']);
        $this->assertEquals(2, $result['retried']);
        Artisan::shouldHaveReceived('call')
            ->once()
            ->with('queue:retry', \Mockery::on(function ($args) use ($expectedUuids) {
                // Check that all expected UUIDs are in the id string (order doesn't matter)
                $passedUuids = explode(',', $args['id']);
                sort($passedUuids);
                sort($expectedUuids);

                return $passedUuids === $expectedUuids;
            }));
    }

    public function test_retry_failed_jobs_scoped_by_signature(): void
    {
        $wantedSignature = sha1('Retry me');
        [$retryId, $retryUuid] = $this->seedFailedJob("Retry me\nstack", 'alpha');
        $this->seedFailedJob('Do not retry', 'beta');

        Artisan::spy();

        $result = $this->manager->retryFailedJobs($wantedSignature);

        $this->assertTrue($result['ok']);
        $this->assertEquals(1, $result['retried']);
        Artisan::shouldHaveReceived('call')
            ->once()
            ->with('queue:retry', ['id' => $retryUuid]);
    }

    /**
     * Seed a failed job and return both numeric ID and UUID.
     *
     * @return array{int, string} [id, uuid]
     */
    private function seedFailedJob(string $message, string $queue, ?Carbon $failedAt = null): array
    {
        $uuid = (string) Str::uuid();
        $id = DB::table('failed_jobs')->insertGetId([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => $queue,
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ExampleJob']),
            'exception' => $message,
            'failed_at' => ($failedAt ?? Carbon::now())->format('Y-m-d H:i:s'),
        ]);

        return [$id, $uuid];
    }
}
