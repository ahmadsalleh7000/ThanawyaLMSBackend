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
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('topic_ar');
            $table->text('prompt');
            $table->text('prompt_ar');
            $table->json('options');
            $table->json('options_ar')->nullable();
            $table->unsignedTinyInteger('correct_option');
            $table->text('explanation');
            $table->text('explanation_ar');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['assessment_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};
