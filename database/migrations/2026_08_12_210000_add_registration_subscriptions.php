<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('billing_period');
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('EGP');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        DB::table('subscription_plans')->insert([
            ['code' => 'semester', 'name_ar' => 'اشتراك فصل دراسي', 'name_en' => 'Semester subscription', 'billing_period' => 'semester', 'price' => 750, 'currency' => 'EGP', 'is_active' => true, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'full-year', 'name_ar' => 'اشتراك العام الكامل', 'name_en' => 'Full-year subscription', 'billing_period' => 'year', 'price' => 1300, 'currency' => 'EGP', 'is_active' => true, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('registration_status')->default('approved')->after('is_active')->index();
            $table->timestamp('registration_reviewed_at')->nullable()->after('registration_status');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')->nullable()->after('course_bundle_id')->constrained()->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['registration_status', 'registration_reviewed_at']);
        });
        Schema::dropIfExists('subscription_plans');
    }
};
