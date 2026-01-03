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
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('is_admin');
            $table->string('provider_id')->nullable()->after('provider_name');
            $table->string('avatar_url')->nullable()->after('provider_id');
            $table->unique(['provider_name', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['provider_name', 'provider_id']);
            $table->dropColumn(['provider_name', 'provider_id', 'avatar_url']);
        });
    }
};
