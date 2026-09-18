<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('correct_option')->nullable()->change();
            $table->json('correct_options')->nullable()->after('correct_option');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table): void {
            $table->dropColumn('correct_options');
            $table->unsignedTinyInteger('correct_option')->nullable(false)->change();
        });
    }
};
