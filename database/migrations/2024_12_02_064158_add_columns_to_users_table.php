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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->string('mobile')->unique()->after('password');
            $table->enum('role', ['admin', 'customer', 'architect','dealer'])->default('customer')->after('mobile');
            $table->string('otp')->nullable()->after('role');
            $table->timestamp('expires_at')->nullable()->after('otp');
            $table->boolean('is_present')->default(true)->after('expires_at');
            $table->string('price_type')->nullable()->after('is_present');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
