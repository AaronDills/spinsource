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
        Schema::create('job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running'); // running, success, failed
            $table->json('totals')->nullable(); // processed, created, updated, skipped, errors, api_calls
            $table->string('last_cursor')->nullable(); // For soft-delta pagination
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Index for finding last successful run quickly
            $table->index(['job_name', 'status', 'finished_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
