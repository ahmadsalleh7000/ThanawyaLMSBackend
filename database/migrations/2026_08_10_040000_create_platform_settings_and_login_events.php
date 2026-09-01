<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('guard')->default('web')->index();
            $table->boolean('successful')->index();
            $table->string('failure_reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['successful', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('platform_settings');
    }
};
