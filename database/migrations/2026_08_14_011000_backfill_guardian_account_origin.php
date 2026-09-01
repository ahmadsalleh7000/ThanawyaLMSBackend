<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $firstInvitationIds = DB::table('guardian_invitations')
            ->join('users', 'users.id', '=', 'guardian_invitations.parent_user_id')
            ->where('users.role', 'parent')
            ->where('users.email', 'like', 'guardian-%@guardian.ahmed-lms.local')
            ->where('users.must_change_password', true)
            ->groupBy('guardian_invitations.parent_user_id')
            ->selectRaw('MIN(guardian_invitations.id) as id')
            ->pluck('id');

        DB::table('guardian_invitations')
            ->whereIn('id', $firstInvitationIds)
            ->update(['guardian_account_was_created' => true]);
    }

    public function down(): void
    {
        // Origin data may have been written by the application after this migration.
        // It cannot be safely distinguished from backfilled data during rollback.
    }
};
