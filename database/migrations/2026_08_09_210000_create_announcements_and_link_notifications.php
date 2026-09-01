<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('title_ar');
            $table->text('message');
            $table->text('message_ar');
            $table->string('target_type')->default('all')->index();
            $table->string('course_id')->nullable();
            $table->string('grade')->nullable();
            $table->string('action_url')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignId('announcement_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('announcement_id'));
        Schema::dropIfExists('announcements');
    }
};
