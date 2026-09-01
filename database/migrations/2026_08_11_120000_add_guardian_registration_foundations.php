<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_e164', 16)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });

        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->string('relationship_type')->default('guardian')->after('student_id');
            $table->timestamp('invited_at')->nullable()->after('linked_at');
            $table->timestamp('verified_at')->nullable()->after('accepted_at');
            $table->timestamp('revoked_at')->nullable()->after('rejected_at');
        });

        Schema::create('guardian_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('parent_phone_e164', 16);
            $table->string('relationship_type')->default('guardian');
            $table->char('token_hash', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'parent_phone_e164']);
            $table->index(['parent_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_invitations');

        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->dropColumn(['relationship_type', 'invited_at', 'verified_at', 'revoked_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone_e164']);
            $table->dropColumn(['phone_e164', 'phone_verified_at']);
        });
    }
};
