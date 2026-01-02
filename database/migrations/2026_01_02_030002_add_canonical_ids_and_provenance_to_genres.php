<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harden canonical IDs and add provenance tracking for genres.
 *
 * Renames:
 *   - wikidata_id → wikidata_qid (for explicit Wikidata Q-ID naming)
 *
 * Adds provenance fields to track data source and sync times.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('genres', function (Blueprint $table) {
            // Rename existing column to more explicit name
            $table->renameColumn('wikidata_id', 'wikidata_qid');
        });

        Schema::table('genres', function (Blueprint $table) {
            // Provenance tracking
            $table->string('source')->nullable()->after('parent_genre_id')
                ->comment('Primary data source: wikidata, musicbrainz, etc.');
            $table->timestamp('source_last_synced_at')->nullable()->after('source')
                ->comment('When this record was last synced from source');
        });
    }

    public function down(): void
    {
        Schema::table('genres', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_last_synced_at']);
        });

        Schema::table('genres', function (Blueprint $table) {
            $table->renameColumn('wikidata_qid', 'wikidata_id');
        });
    }
};
