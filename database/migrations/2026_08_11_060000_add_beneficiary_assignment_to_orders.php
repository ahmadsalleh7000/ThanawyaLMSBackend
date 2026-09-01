<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('beneficiary_user_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_status')->default('pending')->after('status')->index();
            $table->timestamp('assigned_at')->nullable()->after('paid_at');
        });

        DB::table('orders')->update([
            'beneficiary_user_id' => DB::raw('user_id'),
            'assignment_status' => DB::raw("CASE WHEN status = 'completed' THEN 'assigned' ELSE 'pending' END"),
            'assigned_at' => DB::raw("CASE WHEN status = 'completed' THEN paid_at ELSE NULL END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('beneficiary_user_id');
            $table->dropColumn(['assignment_status', 'assigned_at']);
        });
    }
};
