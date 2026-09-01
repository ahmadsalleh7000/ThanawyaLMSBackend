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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('course_id')->unique();
            $table->unsignedSmallInteger('duration_minutes')->default(10);
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
