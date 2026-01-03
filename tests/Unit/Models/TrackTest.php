<?php

namespace Tests\Unit\Models;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_track_factory_creates_valid_model(): void
    {
        $track = Track::factory()->create();

        $this->assertNotNull($track->id);
        $this->assertNotEmpty($track->title);
        $this->assertNotNull($track->album_id);
        $this->assertNotNull($track->musicbrainz_recording_id);
    }

    public function test_track_belongs_to_album(): void
    {
        $album = Album::factory()->create();
        $track = Track::factory()->forAlbum($album)->create();

        $this->assertInstanceOf(Album::class, $track->album);
        $this->assertEquals($album->id, $track->album->id);
    }

    public function test_at_position_factory_state(): void
    {
        $track = Track::factory()->atPosition(5, 2)->create();

        $this->assertEquals(5, $track->position);
        $this->assertEquals('5', $track->number);
        $this->assertEquals(2, $track->disc_number);
    }

    public function test_with_length_factory_state(): void
    {
        $track = Track::factory()->withLength(180)->create();

        $this->assertEquals(180000, $track->length_ms);
    }

    public function test_formatted_length_attribute_short_track(): void
    {
        $track = Track::factory()->create(['length_ms' => 195000]); // 3:15

        $this->assertEquals('3:15', $track->formatted_length);
    }

    public function test_formatted_length_attribute_long_track(): void
    {
        $track = Track::factory()->create(['length_ms' => 3661000]); // 1:01:01

        $this->assertEquals('1:01:01', $track->formatted_length);
    }

    public function test_formatted_length_attribute_null_length(): void
    {
        $track = Track::factory()->create(['length_ms' => null]);

        $this->assertNull($track->formatted_length);
    }

    public function test_track_casts(): void
    {
        $track = Track::factory()->create([
            'position' => '5',
            'disc_number' => '2',
            'length_ms' => '300000',
        ]);

        $this->assertIsInt($track->position);
        $this->assertIsInt($track->disc_number);
        $this->assertIsInt($track->length_ms);
    }
}
