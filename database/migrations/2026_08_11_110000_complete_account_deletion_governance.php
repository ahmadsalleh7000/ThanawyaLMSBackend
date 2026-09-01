<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            $table->foreignId('reviewed_by')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->timestamp('processed_at')->nullable()->after('cancelled_at');
            $table->timestamp('legal_records_until')->nullable()->after('processed_at');
            $table->timestamp('legal_records_pruned_at')->nullable()->after('legal_records_until');
            $table->uuid('anonymization_reference')->nullable()->unique()->after('legal_records_pruned_at');
            $table->text('processing_error')->nullable()->after('anonymization_reference');
            $table->string('policy_version')->nullable()->after('processing_error');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropUnique(['anonymization_reference']);
            $table->dropColumn(['reviewed_at', 'review_note', 'processed_at', 'legal_records_until', 'legal_records_pruned_at', 'anonymization_reference', 'processing_error', 'policy_version']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('anonymized_at'));
    }
};
