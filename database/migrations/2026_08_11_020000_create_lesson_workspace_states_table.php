<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_workspace_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->boolean('is_bookmarked')->default(false);
            $table->unsignedInteger('playback_seconds')->default(0);
            $table->decimal('playback_rate', 3, 2)->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_workspace_states');
    }
};
