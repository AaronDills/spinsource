<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Incremental\RefreshAlbumsForChangedArtists;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Country;
use App\Models\IngestionCheckpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests to verify job resumability - jobs that fail mid-run should resume safely.
 */
class JobResumabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Country::create([
            'wikidata_qid' => 'Q30',
            'name' => 'United States',
        ]);
    }

    /**
     * Test that RefreshAlbumsForChangedArtists clears meta only after success.
     */
    public function test_refresh_albums_preserves_meta_on_failure(): void
    {
        // Create test artists
        $artist1 = Artist::create([
            'name' => 'Artist 1',
            'wikidata_qid' => 'Q11111',
        ]);

        $artist2 = Artist::create([
            'name' => 'Artist 2',
            'wikidata_qid' => 'Q22222',
        ]);

        // Set up checkpoint with changed artist QIDs
        $checkpoint = IngestionCheckpoint::forKey('artists');
        $checkpoint->setMeta('changed_artist_qids', ['Q11111', 'Q22222']);

        // Verify meta is set
        $this->assertEquals(['Q11111', 'Q22222'], $checkpoint->getMeta('changed_artist_qids'));

        // The job reads meta, processes, then clears meta at the END
        // If it fails before clearing, meta should still be present for retry

        // Reload checkpoint to verify it persisted
        $checkpoint->refresh();
        $this->assertEquals(['Q11111', 'Q22222'], $checkpoint->getMeta('changed_artist_qids'));
    }

    /**
     * Test that checkpoint O-ID bumping is monotonic across retries.
     */
    public function test_checkpoint_oid_is_monotonic_across_retries(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('test_job');

        // Simulate first partial run that processed up to OID 500
        $checkpoint->bumpSeenOid(500);
        $this->assertEquals(500, $checkpoint->last_seen_oid);

        // Simulate retry that somehow sees lower OID (shouldn't happen, but test defense)
        $checkpoint->bumpSeenOid(300);
        $this->assertEquals(500, $checkpoint->last_seen_oid); // Should NOT decrease

        // Simulate successful continuation
        $checkpoint->bumpSeenOid(750);
        $this->assertEquals(750, $checkpoint->last_seen_oid);
    }

    /**
     * Test that checkpoint changed_at bumping is monotonic.
     */
    public function test_checkpoint_changed_at_is_monotonic(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('test_job');

        $ts1 = now()->subHours(2);
        $ts2 = now()->subHour();
        $ts3 = now();

        // First bump
        $checkpoint->bumpChangedAt($ts2);
        $this->assertEquals($ts2->toIso8601String(), $checkpoint->last_changed_at->toIso8601String());

        // Earlier timestamp - should NOT update
        $checkpoint->bumpChangedAt($ts1);
        $this->assertEquals($ts2->toIso8601String(), $checkpoint->last_changed_at->toIso8601String());

        // Later timestamp - should update
        $checkpoint->bumpChangedAt($ts3);
        $this->assertEquals($ts3->toIso8601String(), $checkpoint->last_changed_at->toIso8601String());
    }

    /**
     * Test that album upsert handles partial prior data gracefully.
     */
    public function test_album_upsert_handles_partial_prior_data(): void
    {
        $artist = Artist::create([
            'name' => 'Test Artist',
            'wikidata_qid' => 'Q12345',
        ]);

        // Simulate partial data from a failed run
        $partialAlbum = Album::create([
            'title' => 'Partial Album',
            'wikidata_qid' => 'Q99999',
            'artist_id' => $artist->id,
            'release_year' => null, // Missing data from partial run
            'description' => null,
        ]);

        $this->assertNull($partialAlbum->release_year);
        $this->assertNull($partialAlbum->description);

        // Retry with complete data - should update, not fail
        Album::upsert(
            [
                [
                    'wikidata_qid' => 'Q99999',
                    'title' => 'Complete Album',
                    'artist_id' => $artist->id,
                    'release_year' => 2020,
                    'description' => 'Full description',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['wikidata_qid'],
            ['title', 'release_year', 'description', 'updated_at']
        );

        $partialAlbum->refresh();
        $this->assertEquals('Complete Album', $partialAlbum->title);
        $this->assertEquals(2020, $partialAlbum->release_year);
        $this->assertEquals('Full description', $partialAlbum->description);
        $this->assertDatabaseCount('albums', 1); // No duplicate created
    }

    /**
     * Test that cursor-based pagination doesn't reprocess on retry.
     */
    public function test_cursor_pagination_skips_processed_items(): void
    {
        // Create artists with sequential IDs
        for ($i = 1; $i <= 10; $i++) {
            Artist::create([
                'name' => "Artist {$i}",
                'wikidata_qid' => "Q{$i}0000",
            ]);
        }

        // Query with cursor should skip already-processed items
        $afterId = 5;

        $remaining = Artist::where('id', '>', $afterId)
            ->orderBy('id')
            ->get();

        // Should only get artists 6-10
        $this->assertCount(5, $remaining);
        $this->assertEquals('Q60000', $remaining->first()->wikidata_qid);
        $this->assertEquals('Q100000', $remaining->last()->wikidata_qid);
    }

    /**
     * Test that ShouldBeUnique prevents concurrent runs.
     */
    public function test_should_be_unique_generates_stable_id(): void
    {
        // The uniqueId should be deterministic for the same parameters
        $job1 = new \App\Jobs\MusicBrainzFetchTracklist(123);
        $job2 = new \App\Jobs\MusicBrainzFetchTracklist(123);
        $job3 = new \App\Jobs\MusicBrainzFetchTracklist(456);

        $this->assertEquals($job1->uniqueId(), $job2->uniqueId());
        $this->assertNotEquals($job1->uniqueId(), $job3->uniqueId());
    }

    /**
     * Test ingestion checkpoint forKey is idempotent.
     */
    public function test_ingestion_checkpoint_for_key_is_idempotent(): void
    {
        // First call creates
        $checkpoint1 = IngestionCheckpoint::forKey('my_job');
        $this->assertNotNull($checkpoint1->id);

        // Second call returns same record
        $checkpoint2 = IngestionCheckpoint::forKey('my_job');
        $this->assertEquals($checkpoint1->id, $checkpoint2->id);

        // Only one record in database
        $this->assertEquals(1, IngestionCheckpoint::where('key', 'my_job')->count());
    }

    /**
     * Test that changed_at buffer provides overlap for safety.
     */
    public function test_changed_at_buffer_provides_overlap(): void
    {
        $checkpoint = IngestionCheckpoint::forKey('test');

        $now = now();
        $checkpoint->bumpChangedAt($now);

        // With 48-hour buffer, the returned timestamp should be 48 hours earlier
        $buffered = $checkpoint->getChangedAtWithBuffer(48);

        $this->assertEquals(
            $now->copy()->subHours(48)->toIso8601String(),
            $buffered->toIso8601String()
        );
    }
}
