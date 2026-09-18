<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('marked_by')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('marked_at');
            $table->timestamps();
            $table->unique(['live_session_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_attendances');
    }
};
