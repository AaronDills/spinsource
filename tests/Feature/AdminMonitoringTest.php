<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
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
    // Authorization Tests
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_monitoring_index(): void
    {
        $response = $this->get('/admin/monitoring');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_monitoring_data(): void
    {
        $response = $this->getJson('/admin/monitoring/data');

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_clear_failed_jobs(): void
    {
        $response = $this->postJson('/admin/monitoring/clear-failed');

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_monitoring_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/monitoring');

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_access_monitoring_data(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/admin/monitoring/data');

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_clear_failed_jobs(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->postJson('/admin/monitoring/clear-failed');

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Admin Access Tests
    // -------------------------------------------------------------------------

    public function test_admin_can_access_monitoring_index(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get('/admin/monitoring');

        $response->assertOk();
        $response->assertViewIs('admin.monitoring');
    }

    public function test_admin_can_access_monitoring_data(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/admin/monitoring/data');

        $response->assertOk();
        $response->assertJsonStructure([
            'generated_at',
            'generated_at_human',
            'queues',
            'tables',
            'failed_jobs',
            'ingestion_activity',
            'heartbeats',
            'coverage',
            'sync_recency',
            'error_rates',
            'env',
            'warnings',
        ]);
    }

    public function test_monitoring_data_contains_queue_metrics(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/admin/monitoring/data');

        $response->assertOk();
        $response->assertJsonStructure([
            'queues' => [
                'connection',
                'driver',
                'redis_available',
                'queues',
            ],
        ]);
    }

    public function test_monitoring_data_contains_table_counts(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/admin/monitoring/data');

        $response->assertOk();

        // The tables array should exist and contain expected table metrics
        $data = $response->json();
        $this->assertArrayHasKey('tables', $data);
        $this->assertIsArray($data['tables']);
    }

    public function test_monitoring_data_contains_environment_info(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/admin/monitoring/data');

        $response->assertOk();
        $response->assertJsonStructure([
            'env' => [
                'app_env',
                'app_debug',
                'php_version',
                'laravel_version',
                'queue_connection',
                'cache_driver',
                'db_connection',
            ],
        ]);
    }

    public function test_monitoring_data_contains_failed_jobs_info(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/admin/monitoring/data');

        $response->assertOk();
        $response->assertJsonStructure([
            'failed_jobs' => [
                'exists',
                'count',
                'recent',
                'warning',
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Clear Failed Jobs Tests
    // -------------------------------------------------------------------------

    public function test_admin_can_clear_failed_jobs(): void
    {
        $admin = $this->createAdminUser();

        // Add some failed jobs to the table
        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->insert([
                [
                    'uuid' => 'test-uuid-1',
                    'connection' => 'redis',
                    'queue' => 'default',
                    'payload' => '{}',
                    'exception' => 'Test exception 1',
                    'failed_at' => now(),
                ],
                [
                    'uuid' => 'test-uuid-2',
                    'connection' => 'redis',
                    'queue' => 'default',
                    'payload' => '{}',
                    'exception' => 'Test exception 2',
                    'failed_at' => now(),
                ],
            ]);

            $this->assertEquals(2, DB::table('failed_jobs')->count());
        }

        $response = $this->actingAs($admin)->postJson('/admin/monitoring/clear-failed');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        if (Schema::hasTable('failed_jobs')) {
            $this->assertEquals(0, DB::table('failed_jobs')->count());
        }
    }

    public function test_clear_failed_jobs_returns_count_cleared(): void
    {
        $admin = $this->createAdminUser();

        // Add some failed jobs
        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->insert([
                [
                    'uuid' => 'test-uuid-3',
                    'connection' => 'redis',
                    'queue' => 'default',
                    'payload' => '{}',
                    'exception' => 'Test exception',
                    'failed_at' => now(),
                ],
            ]);
        }

        $response = $this->actingAs($admin)->postJson('/admin/monitoring/clear-failed');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'cleared',
            'message',
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertIsInt($data['cleared']);
    }

    public function test_clear_failed_jobs_works_when_table_is_empty(): void
    {
        $admin = $this->createAdminUser();

        // Ensure no failed jobs exist
        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->truncate();
        }

        $response = $this->actingAs($admin)->postJson('/admin/monitoring/clear-failed');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'cleared' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // Warnings Tests
    // -------------------------------------------------------------------------

    public function test_monitoring_data_warnings_is_array(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/admin/monitoring/data');

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('warnings', $data);
        $this->assertIsArray($data['warnings']);
    }
}
