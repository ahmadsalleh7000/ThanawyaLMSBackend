<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('course_bundle_items', function (Blueprint $table) {
            $table->foreignId('course_bundle_id')->constrained()->cascadeOnDelete();
            $table->string('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->primary(['course_bundle_id', 'course_id']);
        });
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 10, 2);
            $table->decimal('minimum_amount', 10, 2)->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_per_user')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('coupon_courses', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->primary(['coupon_id', 'course_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->string('course_id')->nullable()->change();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->string('product_type')->default('course')->after('course_id')->index();
            $table->foreignId('course_bundle_id')->nullable()->after('product_type')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->after('course_bundle_id')->constrained()->nullOnDelete();
            $table->decimal('original_amount', 10, 2)->default(0)->after('coupon_id');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_amount');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2);
            $table->string('status')->default('reserved')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'user_id', 'status']);
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->json('line_items');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->char('currency', 3);
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropConstrainedForeignId('course_bundle_id');
            $table->dropColumn(['product_type', 'original_amount', 'discount_amount']);
            $table->dropForeign(['course_id']);
            $table->string('course_id')->nullable(false)->change();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
        Schema::dropIfExists('coupon_courses');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('course_bundle_items');
        Schema::dropIfExists('course_bundles');
    }
};
