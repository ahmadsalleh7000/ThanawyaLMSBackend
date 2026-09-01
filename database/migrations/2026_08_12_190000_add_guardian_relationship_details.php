<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_invitations', function (Blueprint $table): void {
            $table->string('relationship_details', 80)->nullable()->after('relationship_type');
        });

        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->string('relationship_details', 80)->nullable()->after('relationship_type');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_invitations', function (Blueprint $table): void {
            $table->dropColumn('relationship_details');
        });

        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->dropColumn('relationship_details');
        });
    }
};
