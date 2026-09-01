<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route_name')->nullable()->index();
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code')->index();
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('memory_kb')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['route_name', 'occurred_at']);
        });
        Schema::create('application_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->index();
            $table->string('type')->index();
            $table->string('severity')->index();
            $table->string('title');
            $table->text('message');
            $table->json('context')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->unique(['fingerprint', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_incidents');
        Schema::dropIfExists('request_metrics');
    }
};
