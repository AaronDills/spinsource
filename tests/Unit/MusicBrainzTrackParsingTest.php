<?php

namespace Tests\Unit;

use App\Jobs\MusicBrainzFetchTracklist;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for MusicBrainz track position parsing logic.
 *
 * The city's full of bad data, but we handle it all.
 */
class MusicBrainzTrackParsingTest extends TestCase
{
    /**
     * Test that a vinyl-style track number like "A1" results in fallback position.
     */
    public function test_vinyl_track_number_uses_fallback_position(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'parseTrackPosition');

        // Vinyl track with "A1" as number, no explicit position
        $track = ['number' => 'A1', 'title' => 'Side A Track 1'];
        $position = $method->invoke($job, $track, 1);

        $this->assertSame(1, $position);
    }

    /**
     * Test that explicit numeric position is preferred over fallback.
     */
    public function test_explicit_position_is_preferred(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'parseTrackPosition');

        $track = ['position' => 5, 'number' => '5', 'title' => 'Track 5'];
        $position = $method->invoke($job, $track, 99);

        $this->assertSame(5, $position);
    }

    /**
     * Test that position 0 falls back to counter.
     */
    public function test_position_zero_falls_back_to_counter(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'parseTrackPosition');

        $track = ['position' => 0, 'number' => '0'];
        $position = $method->invoke($job, $track, 3);

        $this->assertSame(3, $position);
    }

    /**
     * Test that missing position and number uses fallback.
     */
    public function test_missing_position_uses_fallback(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'parseTrackPosition');

        $track = ['title' => 'Mystery Track'];
        $position = $method->invoke($job, $track, 7);

        $this->assertSame(7, $position);
    }

    /**
     * Test sequential fallback prevents position collisions.
     */
    public function test_sequential_fallback_prevents_collisions(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'parseTrackPosition');

        // Simulate processing 3 vinyl tracks with only 'number' fields
        $tracks = [
            ['number' => 'A1'],
            ['number' => 'A2'],
            ['number' => 'A3'],
        ];

        $positions = [];
        $fallback = 0;

        foreach ($tracks as $track) {
            $fallback++;
            $positions[] = $method->invoke($job, $track, $fallback);
        }

        $this->assertSame([1, 2, 3], $positions);
        $this->assertCount(3, array_unique($positions), 'Positions should not collide');
    }

    /**
     * Test valid MBIDs pass validation.
     */
    public function test_valid_mbid_passes_validation(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'isValidMbid');

        $this->assertTrue($method->invoke($job, 'abc12345-1234-1234-1234-123456789012'));
        $this->assertTrue($method->invoke($job, 'ABCDEF12-ABCD-ABCD-ABCD-ABCDEF123456'));
        $this->assertTrue($method->invoke($job, '00000000-0000-0000-0000-000000000000'));
    }

    /**
     * Test invalid MBIDs fail validation.
     */
    public function test_invalid_mbid_fails_validation(): void
    {
        $job = new MusicBrainzFetchTracklist(albumId: 1);
        $method = new ReflectionMethod($job, 'isValidMbid');

        // Null/empty
        $this->assertFalse($method->invoke($job, null));
        $this->assertFalse($method->invoke($job, ''));

        // Wrong format
        $this->assertFalse($method->invoke($job, 'not-a-uuid'));
        $this->assertFalse($method->invoke($job, '12345'));
        $this->assertFalse($method->invoke($job, 'abc12345-1234-1234-1234-12345678901')); // Too short
        $this->assertFalse($method->invoke($job, 'abc12345-1234-1234-1234-1234567890123')); // Too long
        $this->assertFalse($method->invoke($job, 'abc12345123412341234123456789012')); // No dashes
        $this->assertFalse($method->invoke($job, 'ggg12345-1234-1234-1234-123456789012')); // Invalid hex
    }
}
