<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_heartbeats')) {
            Schema::create('job_heartbeats', function (Blueprint $table) {
                $table->id();
                $table->string('job')->index();
                $table->string('metric')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_heartbeats');
    }
};
