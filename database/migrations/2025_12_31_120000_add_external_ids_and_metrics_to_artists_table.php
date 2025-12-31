<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            // External IDs from Wikidata
            $table->string('spotify_artist_id')->nullable()->index()->after('musicbrainz_id');
            $table->string('apple_music_artist_id')->nullable()->index()->after('spotify_artist_id');
            $table->string('discogs_artist_id')->nullable()->index()->after('apple_music_artist_id');

            // Heuristic metrics for search ranking
            $table->unsignedInteger('album_count')->default(0)->after('country_id');
            $table->unsignedInteger('link_count')->default(0)->after('album_count');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn([
                'spotify_artist_id',
                'apple_music_artist_id',
                'discogs_artist_id',
                'album_count',
                'link_count',
            ]);
        });
    }
};
