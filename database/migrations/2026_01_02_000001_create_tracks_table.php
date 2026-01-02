<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();

            // Foreign key to albums
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();

            // MusicBrainz identifiers
            $table->string('musicbrainz_recording_id')->nullable()->index();
            $table->string('musicbrainz_release_id')->nullable()->index();

            // Track metadata
            $table->string('title');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('disc_number')->default(1);
            $table->unsignedInteger('length_ms')->nullable();

            $table->timestamps();

            // Composite unique: same track position per album/disc
            $table->unique(['album_id', 'disc_number', 'position']);

            // Index for efficient album tracklist queries
            $table->index(['album_id', 'disc_number', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
