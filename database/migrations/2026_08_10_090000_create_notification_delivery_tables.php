<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject_ar');
            $table->string('subject_en');
            $table->text('body_ar');
            $table->text('body_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('template_key')->index();
            $table->string('channel')->default('email');
            $table->string('recipient');
            $table->string('locale', 5);
            $table->string('subject');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->string('status')->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
        $now = now();
        DB::table('notification_templates')->insert([
            ['key' => 'payment_approved', 'name' => 'Payment approved', 'subject_ar' => 'تم تأكيد الدفع', 'subject_en' => 'Payment approved', 'body_ar' => 'مرحباً {{name}}، تم تأكيد دفع طلبك {{order}} وأصبحت الدورة متاحة.', 'body_en' => 'Hello {{name}}, payment for order {{order}} was approved and your course is now available.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'payment_rejected', 'name' => 'Payment rejected', 'subject_ar' => 'تعذر قبول إثبات الدفع', 'subject_en' => 'Payment evidence rejected', 'body_ar' => 'مرحباً {{name}}، تعذر قبول إثبات الطلب {{order}}. السبب: {{reason}}', 'body_en' => 'Hello {{name}}, evidence for order {{order}} was rejected. Reason: {{reason}}', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'payment_needs_information', 'name' => 'Payment information requested', 'subject_ar' => 'مطلوب استكمال بيانات الدفع', 'subject_en' => 'More payment information required', 'body_ar' => 'مرحباً {{name}}، نحتاج بيانات إضافية للطلب {{order}}: {{reason}}', 'body_en' => 'Hello {{name}}, more information is required for order {{order}}: {{reason}}', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'payment_expired', 'name' => 'Payment expired', 'subject_ar' => 'انتهت مهلة طلب الدفع', 'subject_en' => 'Payment order expired', 'body_ar' => 'مرحباً {{name}}، انتهت مهلة الطلب {{order}}. يمكنك إنشاء طلب جديد.', 'body_en' => 'Hello {{name}}, order {{order}} expired. You can create a new order.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'finance_alert', 'name' => 'Finance alert', 'subject_ar' => 'تنبيه مالي يحتاج المراجعة', 'subject_en' => 'Financial alert requires review', 'body_ar' => 'مرحباً {{name}}، {{message}}', 'body_en' => 'Hello {{name}}, {{message}}', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'finance_daily_summary', 'name' => 'Daily finance summary', 'subject_ar' => 'الملخص المالي اليومي', 'subject_en' => 'Daily finance summary', 'body_ar' => 'مرحباً {{name}}، {{message}}', 'body_en' => 'Hello {{name}}, {{message}}', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
    }
};
