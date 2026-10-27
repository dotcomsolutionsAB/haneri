<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_cart_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique();
            $table->timestamp('anchor_at')->nullable();
            $table->timestamp('first_sent_at')->nullable();
            $table->timestamp('second_sent_at')->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_cart_reminders');
    }
};
