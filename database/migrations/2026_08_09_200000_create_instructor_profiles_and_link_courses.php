<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en');
            $table->string('headline')->nullable();
            $table->string('headline_en')->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_en')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('instructor_profile_id')->nullable()->after('instructor_en')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropConstrainedForeignId('instructor_profile_id'));
        Schema::dropIfExists('instructor_profiles');
    }
};
