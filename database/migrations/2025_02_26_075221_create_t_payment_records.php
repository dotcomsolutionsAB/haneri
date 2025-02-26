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
        Schema::create('t_payment_records', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->string('razorpay_payment_id');
            $table->decimal('amount');
            $table->enum('status', ['pending', 'failed']);
            $table->integer('order_id');
            $table->string('razorpay_order_id');
            $table->integer('user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_payment_records');
    }
};
