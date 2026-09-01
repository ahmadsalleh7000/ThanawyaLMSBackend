<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_bundle_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->default(1)->index();
            $table->string('prerequisite_course_id')->nullable();
            $table->foreign('prerequisite_course_id')->references('id')->on('courses')->nullOnDelete();
        });

        DB::table('course_bundle_items')->orderBy('course_bundle_id')->orderBy('course_id')->get()
            ->groupBy('course_bundle_id')->each(function ($items): void {
                foreach ($items->values() as $index => $item) {
                    DB::table('course_bundle_items')->where('course_bundle_id', $item->course_bundle_id)->where('course_id', $item->course_id)
                        ->update(['position' => $index + 1, 'prerequisite_course_id' => $index ? $items->values()[$index - 1]->course_id : null]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('course_bundle_items', function (Blueprint $table): void {
            $table->dropForeign(['prerequisite_course_id']);
            $table->dropColumn(['position', 'prerequisite_course_id']);
        });
    }
};
