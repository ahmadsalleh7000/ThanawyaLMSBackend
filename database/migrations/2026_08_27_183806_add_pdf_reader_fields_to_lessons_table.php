<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->boolean('content_pdf_enabled')->default(false)->after('video_url');
            $table->foreignId('content_pdf_media_id')->nullable()->after('content_pdf_enabled')->constrained('media_assets')->nullOnDelete();
            $table->string('content_pdf_url', 2048)->nullable()->after('content_pdf_media_id');
            $table->string('content_pdf_title')->nullable()->after('content_pdf_url');
            $table->text('content_pdf_description')->nullable()->after('content_pdf_title');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_pdf_media_id');
            $table->dropColumn(['content_pdf_enabled', 'content_pdf_url', 'content_pdf_title', 'content_pdf_description']);
        });
    }
};
