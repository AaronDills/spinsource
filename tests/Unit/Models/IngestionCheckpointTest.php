<?php

namespace Tests\Unit\Models;

use App\Models\IngestionCheckpoint;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestionCheckpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_checkpoint_factory_creates_valid_model(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->create();

        $this->assertNotNull($checkpoint->id);
        $this->assertNotEmpty($checkpoint->key);
    }

    public function test_for_key_factory_state(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->forKey('test-checkpoint')->create();

        $this->assertEquals('test-checkpoint', $checkpoint->key);
    }

    public function test_with_oid_factory_state(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->withOid(12345)->create();

        $this->assertEquals(12345, $checkpoint->last_seen_oid);
    }

    public function test_with_changed_at_factory_state(): void
    {
        $timestamp = Carbon::now()->subDays(1);
        $checkpoint = IngestionCheckpoint::factory()->withChangedAt($timestamp)->create();

        $this->assertEquals($timestamp->toDateTimeString(), $checkpoint->last_changed_at->toDateTimeString());
    }

    public function test_with_meta_factory_state(): void
    {
        $meta = ['processed' => 100, 'errors' => 5];
        $checkpoint = IngestionCheckpoint::factory()->withMeta($meta)->create();

        $this->assertEquals($meta, $checkpoint->meta);
    }

    public function test_for_key_static_method_creates_if_not_exists(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('new-key');

        $this->assertDatabaseHas('ingestion_checkpoints', ['key' => 'new-key']);
        $this->assertEquals('new-key', $checkpoint->key);
    }

    public function test_for_key_static_method_returns_existing(): void
    {
        $existing = IngestionCheckpoint::factory()->forKey('existing-key')->withOid(100)->create();

        $checkpoint = IngestionCheckpoint::forKey('existing-key');

        $this->assertEquals($existing->id, $checkpoint->id);
        $this->assertEquals(100, $checkpoint->last_seen_oid);
    }

    public function test_bump_seen_oid_increases_value(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->withOid(100)->create();

        $checkpoint->bumpSeenOid(200);

        $this->assertEquals(200, $checkpoint->fresh()->last_seen_oid);
    }

    public function test_bump_seen_oid_does_not_decrease(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->withOid(200)->create();

        $checkpoint->bumpSeenOid(100);

        $this->assertEquals(200, $checkpoint->fresh()->last_seen_oid);
    }

    public function test_bump_changed_at_increases_timestamp(): void
    {
        $original = Carbon::now()->subDays(2);
        $newer = Carbon::now()->subDay();
        $checkpoint = IngestionCheckpoint::factory()->withChangedAt($original)->create();

        $checkpoint->bumpChangedAt($newer);

        $this->assertEquals($newer->toDateTimeString(), $checkpoint->fresh()->last_changed_at->toDateTimeString());
    }

    public function test_bump_changed_at_does_not_decrease(): void
    {
        $original = Carbon::now()->subDay();
        $older = Carbon::now()->subDays(2);
        $checkpoint = IngestionCheckpoint::factory()->withChangedAt($original)->create();

        $checkpoint->bumpChangedAt($older);

        $this->assertEquals($original->toDateTimeString(), $checkpoint->fresh()->last_changed_at->toDateTimeString());
    }

    public function test_get_changed_at_with_buffer(): void
    {
        $timestamp = Carbon::parse('2024-01-15 12:00:00');
        $checkpoint = IngestionCheckpoint::factory()->withChangedAt($timestamp)->create();

        $buffered = $checkpoint->getChangedAtWithBuffer(24);

        $this->assertEquals('2024-01-14 12:00:00', $buffered->toDateTimeString());
    }

    public function test_get_changed_at_with_buffer_returns_null_when_empty(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->create();

        $this->assertNull($checkpoint->getChangedAtWithBuffer());
    }

    public function test_set_and_get_meta(): void
    {
        $checkpoint = IngestionCheckpoint::factory()->create();

        $checkpoint->setMeta('test_key', 'test_value');

        $this->assertEquals('test_value', $checkpoint->getMeta('test_key'));
        $this->assertEquals('default', $checkpoint->getMeta('nonexistent', 'default'));
    }
}
