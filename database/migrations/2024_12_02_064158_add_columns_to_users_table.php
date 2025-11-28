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
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('mobile')->unique()->after('password');
            $table->string('gstin')->nullable()->after('mobile');
            $table->enum('role', ['admin', 'customer', 'architect','dealer'])->default('customer')->after('gstin');
            $table->string('selected_type')->after('role');
            $table->string('otp')->nullable()->after('selected_type');
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
