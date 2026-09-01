<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->index();
            $table->string('disk')->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->string('processing_status')->default('ready')->index();
            $table->text('processing_error')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreignId('media_library_item_id')->nullable()->after('lesson_id')->constrained()->nullOnDelete();
            $table->dropUnique(['path']);
            $table->unique(['lesson_id', 'media_library_item_id']);
        });

        DB::table('media_assets')->orderBy('id')->each(function ($asset): void {
            $itemId = DB::table('media_library_items')->insertGetId([
                'kind' => $asset->kind, 'disk' => $asset->disk, 'path' => $asset->path,
                'original_name' => $asset->original_name, 'mime_type' => $asset->mime_type,
                'size_bytes' => $asset->size_bytes, 'processing_status' => 'ready',
                'created_at' => $asset->created_at, 'updated_at' => $asset->updated_at,
            ]);
            DB::table('media_assets')->where('id', $asset->id)->update(['media_library_item_id' => $itemId]);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropUnique(['lesson_id', 'media_library_item_id']);
            $table->dropConstrainedForeignId('media_library_item_id');
            $table->unique('path');
        });
        Schema::dropIfExists('media_library_items');
    }
};
