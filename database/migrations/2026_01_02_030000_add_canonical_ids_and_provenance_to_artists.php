<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harden canonical IDs and add provenance tracking for artists.
 *
 * Renames:
 *   - wikidata_id → wikidata_qid (for explicit Wikidata Q-ID naming)
 *   - musicbrainz_id → musicbrainz_artist_mbid (for explicit MBID type)
 *
 * Adds provenance fields to track data source and sync times.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            // Rename existing columns to more explicit names
            $table->renameColumn('wikidata_id', 'wikidata_qid');
            $table->renameColumn('musicbrainz_id', 'musicbrainz_artist_mbid');
        });

        Schema::table('artists', function (Blueprint $table) {
            // Provenance tracking
            $table->string('source')->nullable()->after('country_id')
                ->comment('Primary data source: wikidata, musicbrainz, etc.');
            $table->timestamp('source_last_synced_at')->nullable()->after('source')
                ->comment('When this record was last synced from source');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_last_synced_at']);
        });

        Schema::table('artists', function (Blueprint $table) {
            $table->renameColumn('wikidata_qid', 'wikidata_id');
            $table->renameColumn('musicbrainz_artist_mbid', 'musicbrainz_id');
        });
    }
};
