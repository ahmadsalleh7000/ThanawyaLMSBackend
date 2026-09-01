<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->foreignId('handled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('replied_by')->nullable()->after('handled_by')->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable()->after('message');
            $table->text('last_reply_body')->nullable()->after('admin_note');
            $table->timestamp('replied_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropConstrainedForeignId('replied_by');
            $table->dropColumn(['admin_note', 'last_reply_body', 'replied_at']);
        });
    }
};
