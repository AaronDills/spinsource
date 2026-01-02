<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add provenance tracking for tracks.
 *
 * Tracks already have musicbrainz_recording_id and musicbrainz_release_id.
 * This migration adds provenance fields to track data source and sync times.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            // Provenance tracking
            $table->string('source')->nullable()->after('length_ms')
                ->comment('Primary data source: musicbrainz, etc.');
            $table->timestamp('source_last_synced_at')->nullable()->after('source')
                ->comment('When this record was last synced from source');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_last_synced_at']);
        });
    }
};
