<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('channel')->index();
            $table->string('transaction_id')->unique();
            $table->string('sender_identifier', 100);
            $table->timestamp('transferred_at');
            $table->decimal('submitted_amount', 10, 2);
            $table->string('receipt_disk')->default('local');
            $table->string('receipt_path');
            $table->string('receipt_original_name');
            $table->string('receipt_mime', 100);
            $table->unsignedBigInteger('receipt_size');
            $table->char('receipt_sha256', 64)->index();
            $table->text('student_note')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_submissions');
    }
};
