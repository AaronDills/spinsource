<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artist_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();

            // Canonical platform type (string); validated in app layer.
            $table->string('type');

            // Normalized URL.
            $table->string('url');

            // wikidata, musicbrainz, manual, etc.
            $table->string('source')->nullable();

            $table->boolean('is_official')->default(true);

            $table->timestamps();

            $table->unique(['artist_id', 'type', 'url']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_links');
    }
};
