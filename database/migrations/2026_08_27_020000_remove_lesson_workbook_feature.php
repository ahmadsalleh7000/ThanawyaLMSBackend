<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('lesson_sections');

        Schema::table('lessons', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('lessons', 'lesson_number') ? 'lesson_number' : null,
                Schema::hasColumn('lessons', 'estimated_study_time') ? 'estimated_study_time' : null,
                Schema::hasColumn('lessons', 'short_description') ? 'short_description' : null,
                Schema::hasColumn('lessons', 'print_title') ? 'print_title' : null,
                Schema::hasColumn('lessons', 'workbook_enabled') ? 'workbook_enabled' : null,
                Schema::hasColumn('lessons', 'allow_print') ? 'allow_print' : null,
                Schema::hasColumn('lessons', 'allow_pdf_download') ? 'allow_pdf_download' : null,
                Schema::hasColumn('lessons', 'show_answer_key') ? 'show_answer_key' : null,
                Schema::hasColumn('lessons', 'include_workbook_cover') ? 'include_workbook_cover' : null,
                Schema::hasColumn('lessons', 'include_workbook_info') ? 'include_workbook_info' : null,
                Schema::hasColumn('lessons', 'include_workbook_toc') ? 'include_workbook_toc' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        //
    }
};
