<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('scheduled_for');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
