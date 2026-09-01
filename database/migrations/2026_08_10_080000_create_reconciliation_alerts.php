<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('payment_reconciliation_periods')->cascadeOnDelete();
            $table->string('type');
            $table->string('channel')->nullable();
            $table->string('severity')->default('warning');
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('message');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'type', 'channel']);
            $table->index(['resolved_at', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_alerts');
    }
};
