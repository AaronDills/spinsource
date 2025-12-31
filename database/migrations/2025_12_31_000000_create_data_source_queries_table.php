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
        Schema::create('data_source_queries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('data_source');
            $table->string('query_type')->default('sparql');
            $table->text('query');
            $table->text('description')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'data_source']);
            $table->index('data_source');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_source_queries');
    }
};
