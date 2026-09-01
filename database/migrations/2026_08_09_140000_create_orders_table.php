<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('course_id');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EGP');
            $table->string('status')->default('pending');
            $table->string('payment_provider');
            $table->string('payment_method');
            $table->string('session_token_hash', 64)->unique();
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->longText('provider_payload')->nullable();
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
