<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('public_reference', 32)->nullable()->unique()->after('id');
        });

        DB::table('contact_messages')->orderBy('id')->lazyById()->each(function ($message): void {
            do {
                $reference = 'CONTACT-'.Str::upper(Str::random(8));
            } while (DB::table('contact_messages')->where('public_reference', $reference)->exists());

            DB::table('contact_messages')
                ->where('id', $message->id)
                ->update(['public_reference' => $reference]);
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropUnique(['public_reference']);
            $table->dropColumn('public_reference');
        });
    }
};
