<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_invitations', function (Blueprint $table): void {
            $table->boolean('guardian_account_was_created')->default(false)->after('parent_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_invitations', fn (Blueprint $table) => $table->dropColumn('guardian_account_was_created'));
    }
};
