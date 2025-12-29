<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('wikidata_id')->unique();
            $table->string('musicbrainz_id')->nullable()->index();

            $table->text('description')->nullable();
            $table->unsignedSmallInteger('inception_year')->nullable()->index();

            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('parent_genre_id')->nullable()->constrained('genres')->nullOnDelete();

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
