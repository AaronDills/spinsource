<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    protected function createRegularUser(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Index Page
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_jobs_index(): void
    {
        $response = $this->get('/admin/jobs');

        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_jobs_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/jobs');

        $response->assertForbidden();
    }

    public function test_admin_can_access_jobs_index(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get('/admin/jobs');

        $response->assertOk();
        $response->assertViewIs('admin.jobs');
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Data API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_jobs_data(): void
    {
        $response = $this->getJson('/api/admin/jobs/data');

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_jobs_data(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/admin/jobs/data');

        $response->assertForbidden();
    }

    public function test_admin_can_access_jobs_data(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/jobs/data');

        $response->assertOk();
        $response->assertJsonStructure([
            'generated_at',
            'queue_connection',
            'queue_driver',
            'jobs',
            'failed_jobs',
        ]);
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Dispatch API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_dispatch_job(): void
    {
        $response = $this->postJson('/api/admin/jobs/dispatch', [
            'job_key' => 'test_job',
        ]);

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_dispatch_job(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->postJson('/api/admin/jobs/dispatch', [
            'job_key' => 'test_job',
        ]);

        $response->assertForbidden();
    }

    public function test_dispatch_requires_job_key(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/admin/jobs/dispatch', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['job_key']);
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Cancel API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_cancel_job(): void
    {
        $response = $this->postJson('/api/admin/jobs/cancel', [
            'job_key' => 'test_job',
        ]);

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_cancel_job(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->postJson('/api/admin/jobs/cancel', [
            'job_key' => 'test_job',
        ]);

        $response->assertForbidden();
    }

    public function test_cancel_requires_job_key(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/admin/jobs/cancel', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['job_key']);
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Clear Failed API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_clear_failed_jobs(): void
    {
        $response = $this->postJson('/api/admin/jobs/failed/clear');

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_clear_failed_jobs(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->postJson('/api/admin/jobs/failed/clear');

        $response->assertForbidden();
    }

    public function test_admin_can_clear_failed_jobs(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->postJson('/api/admin/jobs/failed/clear');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'cleared',
        ]);
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Retry Failed API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_retry_failed_jobs(): void
    {
        $response = $this->postJson('/api/admin/jobs/failed/retry');

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_retry_failed_jobs(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->postJson('/api/admin/jobs/failed/retry');

        $response->assertForbidden();
    }

    public function test_admin_can_retry_failed_jobs(): void
    {
        $admin = $this->createAdminUser();

        // Add a failed job to retry
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-retry',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
            'exception' => 'Test exception for retry',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/jobs/failed/retry');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'retried',
        ]);
    }

    // -------------------------------------------------------------------------
    // Functional Tests
    // -------------------------------------------------------------------------

    public function test_jobs_data_contains_expected_structure(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/jobs/data');

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('generated_at', $data);
        $this->assertArrayHasKey('queue_connection', $data);
        $this->assertArrayHasKey('queue_driver', $data);
        $this->assertArrayHasKey('jobs', $data);
        $this->assertArrayHasKey('failed_jobs', $data);
        $this->assertIsArray($data['jobs']);
    }

    public function test_failed_jobs_summary_structure(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/jobs/data');

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('failed_jobs', $data);
        $this->assertArrayHasKey('exists', $data['failed_jobs']);
        $this->assertArrayHasKey('count', $data['failed_jobs']);
    }
}
