<?php

namespace Tests\Unit\Models;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use App\Models\UserAlbumRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumTest extends TestCase
{
    use RefreshDatabase;

    public function test_album_factory_creates_valid_model(): void
    {
        $album = Album::factory()->create();

        $this->assertNotNull($album->id);
        $this->assertNotEmpty($album->title);
        $this->assertNotEmpty($album->wikidata_qid);
        $this->assertStringStartsWith('Q', $album->wikidata_qid);
    }

    public function test_album_belongs_to_artist(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        $this->assertInstanceOf(Artist::class, $album->artist);
        $this->assertEquals($artist->id, $album->artist->id);
    }

    public function test_album_has_many_tracks(): void
    {
        $album = Album::factory()->create();
        for ($i = 1; $i <= 10; $i++) {
            Track::factory()->forAlbum($album)->atPosition($i)->create();
        }

        $this->assertCount(10, $album->tracks);
        $this->assertInstanceOf(Track::class, $album->tracks->first());
    }

    public function test_tracks_are_ordered_by_disc_and_position(): void
    {
        $album = Album::factory()->create();

        Track::factory()->forAlbum($album)->atPosition(2, 1)->create(['title' => 'Track 2']);
        Track::factory()->forAlbum($album)->atPosition(1, 2)->create(['title' => 'Disc 2 Track 1']);
        Track::factory()->forAlbum($album)->atPosition(1, 1)->create(['title' => 'Track 1']);

        $tracks = $album->tracks()->get();

        $this->assertEquals('Track 1', $tracks[0]->title);
        $this->assertEquals('Track 2', $tracks[1]->title);
        $this->assertEquals('Disc 2 Track 1', $tracks[2]->title);
    }

    public function test_album_has_many_ratings(): void
    {
        $album = Album::factory()->create();
        $user = User::factory()->create();

        UserAlbumRating::factory()
            ->forAlbum($album)
            ->forUser($user)
            ->create();

        $this->assertCount(1, $album->ratings);
        $this->assertInstanceOf(UserAlbumRating::class, $album->ratings->first());
    }

    public function test_cover_image_url_attribute(): void
    {
        $albumWithCover = Album::factory()->create([
            'cover_image_commons' => 'Album_cover.jpg',
        ]);
        $albumWithoutCover = Album::factory()->create([
            'cover_image_commons' => null,
        ]);

        $this->assertStringContainsString('commons.wikimedia.org', $albumWithCover->cover_image_url);
        $this->assertStringContainsString('Album_cover.jpg', $albumWithCover->cover_image_url);
        $this->assertNull($albumWithoutCover->cover_image_url);
    }

    public function test_with_artist_factory_state(): void
    {
        $album = Album::factory()->withArtist()->create();

        $this->assertNotNull($album->artist_id);
        $this->assertInstanceOf(Artist::class, $album->artist);
    }

    public function test_with_musicbrainz_factory_state(): void
    {
        $album = Album::factory()->withMusicBrainz()->create();

        $this->assertNotNull($album->musicbrainz_release_group_mbid);
        $this->assertNotNull($album->musicbrainz_release_mbid);
        $this->assertNotNull($album->selected_release_mbid);
    }

    public function test_with_tracklist_factory_state(): void
    {
        $album = Album::factory()->withTracklist()->create();

        $this->assertNotNull($album->tracklist_fetched_at);
        $this->assertEquals(1, $album->tracklist_fetch_attempts);
    }

    public function test_needs_tracklist_factory_state(): void
    {
        $album = Album::factory()->needsTracklist()->create();

        $this->assertNotNull($album->musicbrainz_release_group_mbid);
        $this->assertNull($album->tracklist_fetched_at);
    }

    public function test_searchable_array_structure(): void
    {
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $album = Album::factory()->create([
            'title' => 'Test Album',
            'release_year' => 2020,
            'artist_id' => $artist->id,
        ]);

        $searchable = $album->toSearchableArray();

        $this->assertArrayHasKey('id', $searchable);
        $this->assertArrayHasKey('title', $searchable);
        $this->assertArrayHasKey('release_year', $searchable);
        $this->assertArrayHasKey('artist_name', $searchable);
        $this->assertEquals('Test Artist', $searchable['artist_name']);
    }
}
