<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('source_course_id')->nullable()->after('id');
            $table->foreign('source_course_id')->references('id')->on('courses')->nullOnDelete();
        });

        Schema::create('course_versions', function (Blueprint $table) {
            $table->id();
            $table->string('course_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->default('manual');
            $table->string('label')->nullable();
            $table->json('snapshot');
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index(['course_id', 'created_at']);
        });

        Schema::create('scheduled_course_actions', function (Blueprint $table) {
            $table->id();
            $table->string('course_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->timestamp('run_at')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index(['status', 'run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_course_actions');
        Schema::dropIfExists('course_versions');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['source_course_id']);
            $table->dropColumn('source_course_id');
        });
    }
};
