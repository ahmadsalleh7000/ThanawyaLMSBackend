<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_comments', function (Blueprint $table): void {
            $table->dropForeign(['lesson_id']);
            $table->string('course_id')->nullable()->after('id');
            $table->foreignId('lesson_id')->nullable()->change();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index(['course_id', 'parent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_comments', function (Blueprint $table): void {
            $table->dropForeign(['course_id']);
            $table->dropIndex(['course_id', 'parent_id', 'created_at']);
            $table->dropColumn('course_id');
            $table->foreignId('lesson_id')->nullable(false)->constrained()->change();
        });
    }
};
