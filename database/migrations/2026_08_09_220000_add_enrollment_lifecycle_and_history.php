<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('status')->default('active')->after('enrolled_at')->index();
            $table->timestamp('access_expires_at')->nullable()->after('status')->index();
            $table->string('source')->default('self')->after('access_expires_at');
            $table->text('admin_note')->nullable()->after('source');
        });

        Schema::create('enrollment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('from_course_id')->nullable();
            $table->string('to_course_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_events');
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn(['status', 'access_expires_at', 'source', 'admin_note']));
    }
};
