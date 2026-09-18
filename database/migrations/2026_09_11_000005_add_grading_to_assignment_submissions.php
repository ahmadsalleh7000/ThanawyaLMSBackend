<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('score')->nullable()->after('status');
            $table->text('feedback')->nullable()->after('score');
            $table->foreignId('graded_by')->nullable()->after('feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable()->after('graded_by');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->dropForeign(['graded_by']);
            $table->dropColumn(['score', 'feedback', 'graded_by', 'graded_at']);
        });
    }
};
