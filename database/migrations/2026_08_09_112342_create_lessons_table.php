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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('course_id');
            $table->string('slug');
            $table->string('title');
            $table->string('title_en');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('position');
            $table->boolean('is_preview')->default(false);
            $table->string('video_url')->nullable();
            $table->json('transcript')->nullable();
            $table->json('transcript_en')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'slug']);
            $table->unique(['course_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
