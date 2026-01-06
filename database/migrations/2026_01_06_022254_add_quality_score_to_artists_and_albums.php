<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add quality_score column to artists and albums for search ranking.
 *
 * The quality_score is a composite metric derived from data completeness
 * and external ID presence. Higher scores indicate more established/notable entities.
 *
 * Artist scoring factors (0-100 scale):
 * - Wikipedia presence: +15
 * - Description presence: +5
 * - Image presence: +5
 * - Official website: +3
 * - External IDs: Spotify (+10), Apple Music (+5), MusicBrainz (+3), Discogs (+2)
 * - Album count: 6 * ln(album_count + 1), capped
 * - Link count: 2 * ln(link_count + 1), capped
 *
 * Album scoring factors (0-100 scale):
 * - Wikipedia presence: +15
 * - Cover image presence: +10
 * - Description presence: +5
 * - External IDs: Spotify (+10), Apple Music (+5), MusicBrainz RG (+5)
 * - Artist quality bonus: up to +20 based on artist's quality_score
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->unsignedTinyInteger('quality_score')
                ->default(0)
                ->after('link_count')
                ->index()
                ->comment('Composite quality/popularity score for search ranking (0-100)');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->unsignedTinyInteger('quality_score')
                ->default(0)
                ->after('tracklist_fetch_attempts')
                ->index()
                ->comment('Composite quality/popularity score for search ranking (0-100)');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn('quality_score');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn('quality_score');
        });
    }
};
