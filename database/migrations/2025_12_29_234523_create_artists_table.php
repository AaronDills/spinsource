<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('sort_name')->nullable()->index();

            $table->string('wikidata_id')->unique();
            $table->string('musicbrainz_id')->nullable()->index();

            $table->text('description')->nullable();

            $table->string('wikipedia_url')->nullable();
            $table->string('official_website')->nullable();

            // Commons filenames, not full URLs
            $table->string('image_commons')->nullable();
            $table->string('logo_commons')->nullable();
            $table->string('commons_category')->nullable();

            $table->unsignedSmallInteger('formed_year')->nullable()->index();
            $table->unsignedSmallInteger('disbanded_year')->nullable()->index();

            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
