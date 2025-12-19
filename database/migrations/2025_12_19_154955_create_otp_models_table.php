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
        Schema::create('t_mobile_otp', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 10);
            $table->string('otp', 6)->nullable();
            $table->enum('status', ['invalid', 'valid'])->default('invalid');
            $table->timestamps();

            // $table->index('mobile');
            // $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_models');
    }
};
