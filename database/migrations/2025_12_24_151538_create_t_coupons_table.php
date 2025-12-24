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

            $table->string('coupon_code', 100)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('discount_type', ['percentage', 'price'])->default('percentage');
            $table->decimal('discount_value', 5, 2); // e.g. 10.00
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('count')->default(0); // how many times used/allowed (as you decide)
            $table->date('validity'); // validity date

            $table->timestamps(); // created_at, updated_at
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
