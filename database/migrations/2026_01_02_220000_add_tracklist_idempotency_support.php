<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add idempotency support for MusicBrainz tracklist fetching.
 *
 * Changes:
 * 1. Add tracklist timestamps to albums for tracking fetch attempts/successes
 * 2. Add unique constraint on (album_id, musicbrainz_recording_id) to tracks
 *    - Recording MBID is the stable identifier for a performance
 *    - This prevents duplicates when retrying jobs
 * 3. Keep existing (album_id, disc_number, position) index for queries but remove unique constraint
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add tracklist tracking timestamps to albums (idempotent - skip if exists)
        if (! Schema::hasColumn('albums', 'tracklist_attempted_at')) {
            Schema::table('albums', function (Blueprint $table) {
                $table->timestamp('tracklist_attempted_at')->nullable()->after('source_last_synced_at');
                $table->timestamp('tracklist_fetched_at')->nullable()->after('tracklist_attempted_at');
                $table->unsignedSmallInteger('tracklist_fetch_attempts')->default(0)->after('tracklist_fetched_at');
            });
        }

        // Before adding unique constraint, deduplicate existing tracks
        // Keep the track with the lowest position (most canonical) for each (album_id, recording_id) pair
        $duplicates = DB::select("
            SELECT t1.id
            FROM tracks t1
            INNER JOIN (
                SELECT album_id, musicbrainz_recording_id, MIN(id) as keep_id
                FROM tracks
                WHERE musicbrainz_recording_id IS NOT NULL
                GROUP BY album_id, musicbrainz_recording_id
                HAVING COUNT(*) > 1
            ) t2 ON t1.album_id = t2.album_id
                AND t1.musicbrainz_recording_id = t2.musicbrainz_recording_id
                AND t1.id != t2.keep_id
        ");

        $duplicateIds = array_column($duplicates, 'id');
        if (! empty($duplicateIds)) {
            DB::table('tracks')->whereIn('id', $duplicateIds)->delete();
        }

        // Add unique constraint on (album_id, musicbrainz_recording_id) if not exists
        // Note: The original (album_id, disc_number, position) was just an index, not a unique constraint
        $indexExists = DB::select("
            SHOW INDEX FROM tracks WHERE Key_name = 'tracks_album_recording_unique'
        ");

        if (empty($indexExists)) {
            Schema::table('tracks', function (Blueprint $table) {
                $table->unique(['album_id', 'musicbrainz_recording_id'], 'tracks_album_recording_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropUnique('tracks_album_recording_unique');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['tracklist_attempted_at', 'tracklist_fetched_at', 'tracklist_fetch_attempts']);
        });
    }
};
