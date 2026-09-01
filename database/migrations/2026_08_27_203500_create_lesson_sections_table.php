<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_sections');
    }
};
