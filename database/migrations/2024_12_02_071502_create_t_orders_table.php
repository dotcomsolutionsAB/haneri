<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('t_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id'); // User ID invoice_id
            $table->string('invoice_id');
            $table->decimal('total_amount', 10, 2); // Total order amount
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('pending'); // Order status
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending'); // Payment status
            $table->timestamp('mail_sent_at')->nullable()->after('payment_status');
            $table->enum('delivery_status', ['pending', 'accepted', 'arrived', 'completed', 'cancelled'])->default('pending'); // Payment status
            $table->string('shipping_charge');
            $table->text('shipping_address'); // Shipping address
            $table->string('razorpay_order_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_orders');
    }
};
