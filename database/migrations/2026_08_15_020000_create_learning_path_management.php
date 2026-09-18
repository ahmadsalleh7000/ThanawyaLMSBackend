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
            $table->unsignedSmallInteger('position')->default(1)->after('academic_subject_id');
            $table->index(['academic_subject_id', 'position']);
        });
        if (! collect(Schema::getIndexes('courses'))->contains(fn (array $index) => $index['name'] === 'courses_learning_path_subject_index')) {
            Schema::table('courses', fn (Blueprint $table) => $table->index('academic_subject_id', 'courses_learning_path_subject_index'));
        }
        Schema::table('units', function (Blueprint $table): void {
            $table->string('course_id')->nullable()->after('academic_subject_id');
            $table->timestamp('available_from')->nullable()->after('status');
            $table->timestamp('available_until')->nullable()->after('available_from');
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
        foreach (DB::table('units')->get() as $unit) {
            $courseId = DB::table('lessons')->where('unit_id', $unit->id)->value('course_id')
                ?: DB::table('courses')->where('academic_subject_id', $unit->academic_subject_id)->value('id');
            if ($courseId) {
                DB::table('units')->where('id', $unit->id)->update(['course_id' => $courseId]);
            }
        }
        // MySQL may use the old composite unique index to satisfy this FK.
        // Give the FK a stable single-column index before replacing ordering constraints.
        if (! collect(Schema::getIndexes('units'))->contains(fn (array $index) => $index['name'] === 'units_academic_subject_id_index')) {
            Schema::table('units', fn (Blueprint $table) => $table->index('academic_subject_id'));
        }
        Schema::table('units', function (Blueprint $table): void {
            $table->dropUnique(['academic_subject_id', 'slug']);
            $table->dropUnique(['academic_subject_id', 'position']);
            $table->unique(['course_id', 'slug']);
            $table->unique(['course_id', 'position']);
        });
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->default(1)->after('kind');
            $table->string('status')->default('published')->after('position')->index();
            $table->timestamp('available_from')->nullable()->after('status');
            $table->timestamp('available_until')->nullable()->after('available_from');
            $table->index(['lesson_id', 'position']);
        });
        Schema::create('trainings', function (Blueprint $table): void {
            $table->id();
            $table->morphs('trainable');
            $table->string('type')->index();
            $table->string('title_ar');
            $table->string('title_en');
            $table->text('instructions_ar')->nullable();
            $table->text('instructions_en')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('status')->default('draft')->index();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('passing_score')->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamps();
            $table->unique(['trainable_type', 'trainable_id', 'position'], 'training_parent_position_unique');
        });
        Schema::create('question_bank_question_training', function (Blueprint $table): void {
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('question_bank_question_id');
            $table->foreign('question_bank_question_id', 'training_question_bank_fk')
                ->references('id')->on('question_bank_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->primary(['training_id', 'question_bank_question_id'], 'training_question_primary');
        });
        Schema::create('training_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->json('answers')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('status')->default('started')->index();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'training_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_attempts');
        Schema::dropIfExists('question_bank_question_training');
        Schema::dropIfExists('trainings');
        Schema::table('media_assets', fn (Blueprint $table) => $table->dropColumn(['position', 'status', 'available_from', 'available_until']));
        Schema::table('units', function (Blueprint $table): void {
            $table->dropUnique(['course_id', 'slug']);
            $table->dropUnique(['course_id', 'position']);
            $table->dropForeign(['course_id']);
            $table->dropColumn(['course_id', 'available_from', 'available_until']);
            $table->unique(['academic_subject_id', 'slug']);
            $table->unique(['academic_subject_id', 'position']);
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['academic_subject_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
