<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingestion_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedBigInteger('last_seen_oid')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_checkpoints');
    }
};
