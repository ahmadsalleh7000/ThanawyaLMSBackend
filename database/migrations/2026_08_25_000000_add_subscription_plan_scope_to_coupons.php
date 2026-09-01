<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_subscription_plan', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'subscription_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_subscription_plan');
    }
};
