<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('disk')->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->index(['lesson_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
