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
        Schema::create('t_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Unique coupon code
            $table->enum('discount_type', ['fixed', 'percentage']); // Discount type
            $table->decimal('discount_value', 10, 2); // Discount value
            $table->date('expiration_date'); // Expiration date
            $table->integer('usage_limit')->nullable(); // Maximum usage limit
            $table->integer('used_count')->default(0); // Number of times used
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_coupons');
    }
};
