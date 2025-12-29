<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_album_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();

            // 1-10 integer scale (adjust later if desired)
            $table->unsignedTinyInteger('rating')->nullable()->index();

            $table->date('listened_at')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'album_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_album_ratings');
    }
};
