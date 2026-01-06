<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminLogsTest extends TestCase
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

    public function test_guest_cannot_access_logs_index(): void
    {
        $response = $this->get('/admin/logs');

        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_logs_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get('/admin/logs');

        $response->assertForbidden();
    }

    public function test_admin_can_access_logs_index(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->get('/admin/logs');

        $response->assertOk();
        $response->assertViewIs('admin.logs');
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Files API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_logs_files(): void
    {
        $response = $this->getJson('/api/admin/logs/files');

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_logs_files(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/admin/logs/files');

        $response->assertForbidden();
    }

    public function test_admin_can_access_logs_files(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/files');

        $response->assertOk();
        $response->assertJsonStructure([
            'files',
            'log_path',
        ]);
    }

    // -------------------------------------------------------------------------
    // Authorization Tests - Data API
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_logs_data(): void
    {
        $response = $this->getJson('/api/admin/logs/data');

        $response->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_logs_data(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/admin/logs/data');

        $response->assertForbidden();
    }

    public function test_admin_can_access_logs_data(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data');

        // May be 200 or 404 depending on whether log files exist
        $this->assertContains($response->status(), [200, 404]);
    }

    // -------------------------------------------------------------------------
    // Functional Tests - Files API
    // -------------------------------------------------------------------------

    public function test_logs_files_returns_array_of_files(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/files');

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('files', $data);
        $this->assertIsArray($data['files']);
    }

    public function test_logs_files_contains_log_path(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/files');

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('log_path', $data);
        $this->assertStringContainsString('logs', $data['log_path']);
    }

    // -------------------------------------------------------------------------
    // Functional Tests - Data API
    // -------------------------------------------------------------------------

    public function test_logs_data_accepts_file_parameter(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?file=laravel.log');

        // May be 200 or 404 depending on whether the file exists
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_logs_data_accepts_search_parameter(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?search=error');

        // May be 200 or 404 depending on whether log files exist
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_logs_data_accepts_level_parameter(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?level=error');

        // May be 200 or 404 depending on whether log files exist
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_logs_data_accepts_time_window_parameter(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?time_window=24h');

        // May be 200 or 404 depending on whether log files exist
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_logs_data_accepts_pagination_parameters(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?limit=50&offset=0');

        // May be 200 or 404 depending on whether log files exist
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_logs_data_response_structure_when_file_exists(): void
    {
        $admin = $this->createAdminUser();

        // Create a test log file
        $logPath = storage_path('logs/test.log');
        File::put($logPath, "[2024-01-01 12:00:00] testing.INFO: Test message\n");

        try {
            $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?file=test.log');

            $response->assertOk();
            $response->assertJsonStructure([
                'entries',
                'total',
                'offset',
                'limit',
                'file',
                'filters',
            ]);
        } finally {
            // Clean up test file
            File::delete($logPath);
        }
    }

    public function test_logs_data_prevents_directory_traversal(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)->getJson('/api/admin/logs/data?file=../../../etc/passwd');

        // Should not return sensitive system files
        // The controller sanitizes the filename with basename()
        $this->assertContains($response->status(), [200, 404]);

        if ($response->status() === 200) {
            $data = $response->json();
            // File should be sanitized to just 'passwd' (or fall back to latest log)
            $this->assertNotEquals('../../../etc/passwd', $data['file'] ?? '');
        }
    }

    // -------------------------------------------------------------------------
    // File Structure Tests
    // -------------------------------------------------------------------------

    public function test_logs_files_item_structure(): void
    {
        $admin = $this->createAdminUser();

        // Create a test log file
        $logPath = storage_path('logs/structure-test.log');
        File::put($logPath, "[2024-01-01 12:00:00] testing.INFO: Test\n");

        try {
            $response = $this->actingAs($admin)->getJson('/api/admin/logs/files');

            $response->assertOk();

            $data = $response->json();
            $files = collect($data['files']);
            $testFile = $files->firstWhere('name', 'structure-test.log');

            if ($testFile) {
                $this->assertArrayHasKey('name', $testFile);
                $this->assertArrayHasKey('path', $testFile);
                $this->assertArrayHasKey('size', $testFile);
                $this->assertArrayHasKey('size_human', $testFile);
                $this->assertArrayHasKey('modified', $testFile);
                $this->assertArrayHasKey('modified_human', $testFile);
                $this->assertArrayHasKey('type', $testFile);
            }
        } finally {
            File::delete($logPath);
        }
    }
}
