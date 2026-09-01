<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliation_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('open')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_note')->nullable();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
        Schema::create('payment_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('payment_reconciliation_periods')->cascadeOnDelete();
            $table->string('channel');
            $table->decimal('expected_inflow', 12, 2)->default(0);
            $table->decimal('expected_refunds', 12, 2)->default(0);
            $table->decimal('actual_inflow', 12, 2)->nullable();
            $table->decimal('actual_refunds', 12, 2)->nullable();
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('refund_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['period_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_lines');
        Schema::dropIfExists('payment_reconciliation_periods');
    }
};
