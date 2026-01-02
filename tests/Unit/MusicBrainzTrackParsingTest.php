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
}
