<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_published')->index();
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};
