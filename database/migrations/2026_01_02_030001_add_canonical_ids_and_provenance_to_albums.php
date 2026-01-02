<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harden canonical IDs and add provenance tracking for albums.
 *
 * Renames:
 *   - wikidata_id → wikidata_qid (for explicit Wikidata Q-ID naming)
 *   - musicbrainz_release_group_id → musicbrainz_release_group_mbid
 *
 * Adds:
 *   - musicbrainz_release_mbid: The canonical release for this album
 *   - selected_release_mbid: The specific release we fetched tracks from
 *   - Provenance fields to track data source and sync times
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            // Rename existing columns to more explicit names
            $table->renameColumn('wikidata_id', 'wikidata_qid');
            $table->renameColumn('musicbrainz_release_group_id', 'musicbrainz_release_group_mbid');
        });

        Schema::table('albums', function (Blueprint $table) {
            // New MusicBrainz IDs
            $table->string('musicbrainz_release_mbid')->nullable()->after('musicbrainz_release_group_mbid')
                ->comment('Canonical MusicBrainz release MBID');
            $table->string('selected_release_mbid')->nullable()->after('musicbrainz_release_mbid')
                ->comment('Release MBID used for tracklist import');

            // Provenance tracking
            $table->string('source')->nullable()->after('cover_image_commons')
                ->comment('Primary data source: wikidata, musicbrainz, etc.');
            $table->timestamp('source_last_synced_at')->nullable()->after('source')
                ->comment('When this record was last synced from source');

            // Indexes for new MBID columns
            $table->index('musicbrainz_release_mbid');
            $table->index('selected_release_mbid');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex(['musicbrainz_release_mbid']);
            $table->dropIndex(['selected_release_mbid']);
            $table->dropColumn([
                'musicbrainz_release_mbid',
                'selected_release_mbid',
                'source',
                'source_last_synced_at',
            ]);
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->renameColumn('wikidata_qid', 'wikidata_id');
            $table->renameColumn('musicbrainz_release_group_mbid', 'musicbrainz_release_group_id');
        });
    }
};
