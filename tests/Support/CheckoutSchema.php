<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal schema for the checkout / payment flow. The repo's migrations no longer match the live
 * database, so tests (and local smoke runs) build only the tables and columns the flow touches.
 */
class CheckoutSchema
{
    public static function build(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('mobile')->nullable();
            $t->string('role')->default('customer');
            $t->string('selected_type')->nullable();
            $t->string('gstin')->nullable();
            $t->string('otp')->nullable();
            $t->string('google_id')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('t_mobile_otp', function (Blueprint $t) {
            $t->id();
            $t->string('mobile', 10);
            $t->string('otp', 6)->nullable();
            $t->string('status')->default('invalid');
            $t->timestamps();
        });
        Schema::create('t_products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->decimal('price', 10, 2)->nullable();
            $t->boolean('is_ecommerce')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('t_product_variants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('variant_type')->nullable();
            $t->string('variant_value')->nullable();
            $t->decimal('regular_price', 10, 2);
            $t->decimal('customer_discount', 5, 2)->default(0);
            $t->decimal('dealer_discount', 5, 2)->default(0);
            $t->decimal('architect_discount', 5, 2)->default(0);
            $t->integer('is_cod')->default(0);
            $t->string('photo_id')->nullable();
            $t->decimal('weight', 8, 3)->nullable();
            $t->timestamps();
        });
        Schema::create('t_uploads', function (Blueprint $t) {
            $t->id();
            $t->string('file_path')->nullable();
            $t->timestamps();
        });
        Schema::create('t_users_discount', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('product_variant_id');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->decimal('discount', 5, 2);
            $t->timestamps();
        });
        Schema::create('t_carts', function (Blueprint $t) {
            $t->id();
            $t->string('user_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->nullable();
            $t->integer('quantity');
            $t->timestamps();
        });
        Schema::create('t_orders', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->string('invoice_id')->nullable();
            $t->decimal('total_amount', 10, 2);
            $t->string('status')->default('pending');
            $t->string('payment_status')->default('pending');
            $t->timestamp('mail_sent_at')->nullable();
            $t->string('delivery_status')->default('pending');
            $t->string('shipping_charge')->default('0');
            $t->text('shipping_address');
            $t->string('razorpay_order_id')->nullable();
            $t->timestamps();
        });
        Schema::create('t_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variant_id')->nullable();
            $t->integer('quantity');
            $t->decimal('price', 10, 2);
            $t->timestamps();
        });
        Schema::create('t_payment_records', function (Blueprint $t) {
            $t->id();
            $t->string('method');
            $t->string('razorpay_payment_id')->nullable();
            $t->decimal('amount', 10, 2);
            $t->string('status')->default('pending');
            $t->integer('order_id');
            $t->string('razorpay_order_id');
            $t->integer('user');
            $t->timestamps();
        });
        Schema::create('t_order_shipments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('user_id');
            $t->string('courier')->nullable();
            $t->string('status')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('customer_phone')->nullable();
            $t->string('customer_email')->nullable();
            $t->text('shipping_address')->nullable();
            $t->string('shipping_pin')->nullable();
            $t->string('shipping_city')->nullable();
            $t->string('shipping_state')->nullable();
            $t->string('payment_mode')->nullable();
            $t->decimal('total_amount', 10, 2)->default(0);
            $t->decimal('cod_amount', 10, 2)->default(0);
            $t->integer('quantity')->default(1);
            $t->string('weight')->nullable();
            $t->string('products_description')->nullable();
            $t->unsignedBigInteger('pickup_location_id')->nullable();
            $t->string('pickup_name')->nullable();
            $t->text('pickup_address')->nullable();
            $t->string('pickup_pin')->nullable();
            $t->string('pickup_city')->nullable();
            $t->string('pickup_state')->nullable();
            $t->string('pickup_phone')->nullable();
            $t->timestamps();
        });
        Schema::create('t_addresses', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('name');
            $t->string('contact_no');
            $t->string('address_line1');
            $t->string('address_line2')->nullable();
            $t->string('city');
            $t->string('state');
            $t->string('postal_code');
            $t->string('country');
            $t->boolean('is_default')->default(false);
            $t->string('gst_no')->nullable();
            $t->timestamps();
        });
        Schema::create('t_coupons', function (Blueprint $t) {
            $t->id();
            $t->string('coupon_code')->unique();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('discount_type')->default('percentage');
            $t->decimal('discount_value', 8, 2);
            $t->string('status')->default('active');
            $t->unsignedInteger('count')->default(0);
            $t->date('validity');
            $t->timestamps();
        });
        Schema::create('email_logs', function (Blueprint $t) {
            $t->id();
            $t->string('recipient_email');
            $t->unsignedBigInteger('recipient_user_id')->nullable();
            $t->string('mailable_class');
            $t->string('subject')->nullable();
            $t->string('status', 20);
            $t->text('error_message')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }
}
