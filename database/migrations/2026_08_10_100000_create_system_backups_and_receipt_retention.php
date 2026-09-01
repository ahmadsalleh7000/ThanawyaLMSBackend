<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('database_driver');
            $table->string('status')->default('queued')->index();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('content_sha256', 64)->nullable();
            $table->boolean('is_encrypted')->default(true);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::table('manual_payment_submissions', function (Blueprint $table) {
            $table->timestamp('receipt_deleted_at')->nullable()->after('receipt_sha256')->index();
        });
    }

    public function down(): void
    {
        Schema::table('manual_payment_submissions', fn (Blueprint $table) => $table->dropColumn('receipt_deleted_at'));
        Schema::dropIfExists('system_backups');
    }
};
