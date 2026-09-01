<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->boolean('workbook_enabled')->default(true)->after('content_pdf_description');
            $table->boolean('allow_print')->default(true)->after('workbook_enabled');
            $table->boolean('allow_pdf_download')->default(true)->after('allow_print');
            $table->boolean('include_workbook_cover')->default(true)->after('allow_pdf_download');
            $table->boolean('include_workbook_info')->default(true)->after('include_workbook_cover');
            $table->boolean('include_workbook_toc')->default(true)->after('include_workbook_info');
            $table->boolean('include_workbook_answer_key')->default(false)->after('include_workbook_toc');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn([
                'workbook_enabled',
                'allow_print',
                'allow_pdf_download',
                'include_workbook_cover',
                'include_workbook_info',
                'include_workbook_toc',
                'include_workbook_answer_key',
            ]);
        });
    }
};
