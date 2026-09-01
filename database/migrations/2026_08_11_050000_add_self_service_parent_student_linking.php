<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->string('status')->default('accepted')->after('grade')->index();
            $table->timestamp('accepted_at')->nullable()->after('linked_at');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
        });
        DB::table('parent_student_links')->update(['accepted_at' => DB::raw('linked_at')]);

        Schema::create('student_link_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_link_codes');
        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->dropColumn(['status', 'accepted_at', 'rejected_at']);
        });
    }
};
