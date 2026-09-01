<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->json('objectives')->nullable()->after('content_pdf_description');
            $table->json('key_points')->nullable()->after('objectives');
            $table->text('summary')->nullable()->after('key_points');
            $table->json('completion_rules')->nullable()->after('summary');
        });

        Schema::table('lesson_workspace_states', function (Blueprint $table): void {
            $table->timestamp('content_opened_at')->nullable()->after('playback_rate');
            $table->timestamp('practice_passed_at')->nullable()->after('content_opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_workspace_states', function (Blueprint $table): void {
            $table->dropColumn(['content_opened_at', 'practice_passed_at']);
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn(['objectives', 'key_points', 'summary', 'completion_rules']);
        });
    }
};
