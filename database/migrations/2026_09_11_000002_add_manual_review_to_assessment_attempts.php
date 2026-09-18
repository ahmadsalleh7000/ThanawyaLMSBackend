<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('manual_score')->nullable()->after('score');
            $table->text('manual_feedback')->nullable()->after('manual_score');
            $table->foreignId('manually_reviewed_by')->nullable()->after('manual_feedback')->constrained('users')->nullOnDelete();
            $table->timestamp('manually_reviewed_at')->nullable()->after('manually_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table): void {
            $table->dropForeign(['manually_reviewed_by']);
            $table->dropColumn(['manual_score', 'manual_feedback', 'manually_reviewed_by', 'manually_reviewed_at']);
        });
    }
};
