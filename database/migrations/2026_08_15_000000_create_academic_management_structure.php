<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('academic_grades', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_grade_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name_ar');
            $table->string('name_en');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['academic_year_id', 'academic_grade_id', 'code']);
            $table->index(['academic_grade_id', 'is_active']);
        });
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('academic_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['academic_term_id', 'subject_id']);
        });
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_subject_id')->constrained()->restrictOnDelete();
            $table->string('slug');
            $table->string('title_ar');
            $table->string('title_en');
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('status')->default('draft')->index();
            $table->timestamps();
            $table->unique(['academic_subject_id', 'slug']);
            $table->unique(['academic_subject_id', 'position']);
        });
        Schema::create('subscription_plan_academic_term', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->primary(['subscription_plan_id', 'academic_term_id']);
        });
        Schema::create('student_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_term_id', 'order_id'], 'student_term_order_unique');
            $table->index(['student_id', 'status', 'ends_at']);
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('academic_subject_id')->nullable()->after('category')->constrained()->restrictOnDelete();
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('course_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('published')->after('is_preview')->index();
            $table->timestamp('available_from')->nullable()->after('status')->index();
            $table->timestamp('available_until')->nullable()->after('available_from')->index();
        });

        $this->seedAndBackfill();
    }

    private function seedAndBackfill(): void
    {
        $now = now();
        $year = (int) $now->format('Y');
        $yearId = DB::table('academic_years')->insertGetId(['code' => $year.'-'.($year + 1), 'name_ar' => 'العام الدراسي '.$year.'/'.($year + 1), 'name_en' => 'Academic year '.$year.'/'.($year + 1), 'starts_on' => $year.'-09-01', 'ends_on' => ($year + 1).'-08-31', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $gradeNames = ['grade-1' => ['الأول الثانوي', 'First Secondary'], 'grade-2' => ['الثاني الثانوي', 'Second Secondary'], 'grade-3' => ['الثالث الثانوي', 'Third Secondary']];
        $terms = [];
        foreach ($gradeNames as $gradeCode => [$ar, $en]) {
            $gradeId = DB::table('academic_grades')->insertGetId(['code' => $gradeCode, 'name_ar' => $ar, 'name_en' => $en, 'display_order' => (int) substr($gradeCode, -1), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach (['semester-1' => ['الفصل الدراسي الأول', 'Semester 1'], 'semester-2' => ['الفصل الدراسي الثاني', 'Semester 2']] as $termCode => [$termAr, $termEn]) {
                $terms[$gradeCode][$termCode] = DB::table('academic_terms')->insertGetId(['academic_year_id' => $yearId, 'academic_grade_id' => $gradeId, 'code' => $termCode, 'name_ar' => $termAr, 'name_en' => $termEn, 'display_order' => (int) substr($termCode, -1), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $subjectIds = [];
        foreach (DB::table('courses')->select('category')->distinct()->pluck('category') as $category) {
            $baseCode = str($category)->slug()->limit(70, '')->toString() ?: 'subject';
            $code = $baseCode.'-'.substr(sha1($category), 0, 6);
            $subjectIds[$category] = DB::table('subjects')->insertGetId(['code' => $code, 'name_ar' => $category, 'name_en' => $category, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        if ($subjectIds === []) {
            $subjectIds['Programming'] = DB::table('subjects')->insertGetId(['code' => 'programming', 'name_ar' => 'البرمجة', 'name_en' => 'Programming', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (collect($terms)->flatten() as $termId) {
            foreach ($subjectIds as $subjectId) {
                DB::table('academic_subjects')->insertOrIgnore(['academic_term_id' => $termId, 'subject_id' => $subjectId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach (DB::table('courses')->orderBy('id')->get() as $course) {
            $termId = $terms[$course->grade][$course->semester] ?? null;
            $subjectId = $subjectIds[$course->category] ?? null;
            if (! $termId || ! $subjectId) {
                continue;
            }
            $offeringId = DB::table('academic_subjects')->where(['academic_term_id' => $termId, 'subject_id' => $subjectId])->value('id') ?: DB::table('academic_subjects')->insertGetId(['academic_term_id' => $termId, 'subject_id' => $subjectId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('courses')->where('id', $course->id)->update(['academic_subject_id' => $offeringId]);
            if ($course->unit) {
                $slug = str($course->unit)->slug()->limit(80, '')->toString() ?: 'unit';
                $unitId = DB::table('units')->where(['academic_subject_id' => $offeringId, 'slug' => $slug])->value('id');
                if (! $unitId) {
                    $position = (int) DB::table('units')->where('academic_subject_id', $offeringId)->max('position') + 1;
                    $unitId = DB::table('units')->insertGetId(['academic_subject_id' => $offeringId, 'slug' => $slug, 'title_ar' => $course->unit, 'title_en' => $course->unit, 'position' => $position, 'status' => $course->is_published ? 'published' : 'draft', 'created_at' => $now, 'updated_at' => $now]);
                }
                DB::table('lessons')->where('course_id', $course->id)->update(['unit_id' => $unitId]);
            }
        }
        foreach (DB::table('subscription_plans')->get() as $plan) {
            foreach (collect($terms)->flatten() as $termId) {
                DB::table('subscription_plan_academic_term')->insertOrIgnore(['subscription_plan_id' => $plan->id, 'academic_term_id' => $termId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['status', 'available_from', 'available_until']);
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropConstrainedForeignId('academic_subject_id'));
        Schema::dropIfExists('student_subscriptions');
        Schema::dropIfExists('subscription_plan_academic_term');
        Schema::dropIfExists('units');
        Schema::dropIfExists('academic_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_grades');
        Schema::dropIfExists('academic_years');
    }
};
