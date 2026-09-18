<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_training_id')->constrained('trainings')->cascadeOnDelete();
            $table->unsignedTinyInteger('minimum_score')->default(70);
            $table->timestamps();
            $table->unique(['training_id', 'prerequisite_training_id'], 'training_prereq_training_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_prerequisites');
    }
};
