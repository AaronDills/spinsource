<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Add the new column (nullable at first)
        if (! Schema::hasColumn('countries', 'wikidata_qid')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->string('wikidata_qid')->nullable()->after('name');
            });
        }

        // 2) Copy data over (only if old column exists)
        if (Schema::hasColumn('countries', 'wikidata_id')) {
            DB::statement('UPDATE countries SET wikidata_qid = wikidata_id WHERE wikidata_qid IS NULL AND wikidata_id IS NOT NULL');
        }

        // 3) Drop old unique index (name is usually countries_wikidata_id_unique)
        try {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropUnique('countries_wikidata_id_unique');
            });
        } catch (\Throwable $e) {
            // ignore if index doesn't exist / different name in some environments
        }

        // 4) Drop old column
        if (Schema::hasColumn('countries', 'wikidata_id')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropColumn('wikidata_id');
            });
        }

        // 5) Add unique index on new column
        try {
            Schema::table('countries', function (Blueprint $table) {
                $table->unique('wikidata_qid');
            });
        } catch (\Throwable $e) {
            // ignore if already exists
        }
    }

    public function down(): void
    {
        // 1) Add old column back
        if (! Schema::hasColumn('countries', 'wikidata_id')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->string('wikidata_id')->nullable()->after('name');
            });
        }

        // 2) Copy data back
        DB::statement('UPDATE countries SET wikidata_id = wikidata_qid WHERE wikidata_id IS NULL AND wikidata_qid IS NOT NULL');

        // 3) Drop unique on wikidata_qid
        try {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropUnique('countries_wikidata_qid_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        // 4) Drop new column
        if (Schema::hasColumn('countries', 'wikidata_qid')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropColumn('wikidata_qid');
            });
        }

        // 5) Restore unique on old column
        try {
            Schema::table('countries', function (Blueprint $table) {
                $table->unique('wikidata_id');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
