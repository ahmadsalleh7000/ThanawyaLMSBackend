<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lessons', 'comments_enabled')) {
            Schema::table('lessons', function (Blueprint $table): void {
                $table->boolean('comments_enabled')->default(true)->after('content_pdf_enabled');
            });
        }

        if (! Schema::hasTable('lesson_comments')) {
            Schema::create('lesson_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('lesson_comments')->cascadeOnDelete();
                $table->text('content');
                $table->timestamp('edited_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['lesson_id', 'parent_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_comments');

        if (Schema::hasColumn('lessons', 'comments_enabled')) {
            Schema::table('lessons', function (Blueprint $table): void {
                $table->dropColumn('comments_enabled');
            });
        }
    }
};
