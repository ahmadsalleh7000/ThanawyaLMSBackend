<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('refund_provider_id')->nullable()->after('provider_payment_id');
            $table->timestamp('refunded_at')->nullable()->after('failed_at');
            $table->text('refund_reason')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['refund_provider_id', 'refunded_at', 'refund_reason']);
        });
    }
};
