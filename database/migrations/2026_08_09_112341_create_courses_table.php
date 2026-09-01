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
        Schema::create('courses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('title_en');
            $table->text('summary');
            $table->text('summary_en');
            $table->text('description')->nullable();
            $table->text('description_en')->nullable();
            $table->string('category');
            $table->string('grade')->index();
            $table->string('semester')->index();
            $table->string('unit')->nullable();
            $table->string('track_type')->nullable();
            $table->string('level');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->string('instructor');
            $table->string('instructor_en');
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
