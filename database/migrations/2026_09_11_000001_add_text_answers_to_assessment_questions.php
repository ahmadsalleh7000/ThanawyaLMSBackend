<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table): void {
            $table->text('correct_answer')->nullable()->after('correct_options');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table): void {
            $table->dropColumn('correct_answer');
        });
    }
};
