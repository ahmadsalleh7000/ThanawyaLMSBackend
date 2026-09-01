<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('scope', 24)->default('academic')->after('category')->index();
            $table->string('grade')->nullable()->change();
            $table->string('semester')->nullable()->change();
        });

        DB::table('courses')->update(['scope' => 'academic']);
    }

    public function down(): void
    {
        DB::table('courses')->whereNull('grade')->update(['grade' => 'grade-1', 'semester' => 'semester-1']);
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
            $table->string('grade')->nullable(false)->change();
            $table->string('semester')->nullable(false)->change();
        });
    }
};
