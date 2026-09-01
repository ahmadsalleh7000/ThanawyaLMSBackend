<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->foreignId('guardian_invitation_id')->nullable()->after('user_id')
                ->constrained('guardian_invitations')->nullOnDelete();
            $table->string('provider_message_id')->nullable()->after('attempts');
            $table->index(['guardian_invitation_id', 'channel', 'created_at'], 'guardian_delivery_audit_index');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('guardian_delivery_audit_index');
            $table->dropConstrainedForeignId('guardian_invitation_id');
            $table->dropColumn('provider_message_id');
        });
    }
};
