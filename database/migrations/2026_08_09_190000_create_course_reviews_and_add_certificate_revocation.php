<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course_id');
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->string('status')->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_note')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('issued_at')->index();
            $table->text('revocation_reason')->nullable()->after('revoked_at');
            $table->foreignId('revoked_by')->nullable()->after('revocation_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revocation_reason']);
        });
        Schema::dropIfExists('course_reviews');
    }
};
