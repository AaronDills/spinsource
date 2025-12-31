<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            // External IDs from Wikidata
            $table->string('spotify_album_id')->nullable()->index()->after('musicbrainz_release_group_id');
            $table->string('apple_music_album_id')->nullable()->index()->after('spotify_album_id');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn([
                'spotify_album_id',
                'apple_music_album_id',
            ]);
        });
    }
};
