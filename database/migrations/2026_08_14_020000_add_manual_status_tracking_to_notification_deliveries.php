<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->timestamp('opened_at')->nullable()->after('last_error');
            $table->foreignId('sent_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->index(['channel', 'status', 'created_at'], 'notification_manual_status_index');
        });

        DB::table('notification_deliveries')
            ->where('channel', 'whatsapp_web')
            ->where('status', 'opened')
            ->update([
                'status' => 'pending',
                'opened_at' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notification_manual_status_index');
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn('opened_at');
        });
    }
};
