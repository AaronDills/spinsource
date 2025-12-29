<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('wikidata_id')->unique();
            $table->string('musicbrainz_release_group_id')->nullable()->index();

            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();

            $table->string('album_type')->default('album')->index();
            $table->unsignedSmallInteger('release_year')->nullable()->index();
            $table->date('release_date')->nullable()->index();

            $table->text('description')->nullable();
            $table->string('wikipedia_url')->nullable();

            $table->timestamps();

            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
