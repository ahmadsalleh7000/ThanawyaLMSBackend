<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('registration_reviewed_by')->nullable()->after('registration_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('registration_rejection_reason')->nullable()->after('registration_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('registration_reviewed_by');
            $table->dropColumn('registration_rejection_reason');
        });
    }
};
