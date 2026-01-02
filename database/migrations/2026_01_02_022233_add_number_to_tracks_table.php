<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds 'number' column to store raw MusicBrainz track numbers (e.g., "A1", "B2" for vinyl).
     * The existing 'position' column remains as the numeric sort position.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('number')->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
