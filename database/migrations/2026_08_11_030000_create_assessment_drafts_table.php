<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->unsignedInteger('current_index')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['assessment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_drafts');
    }
};
