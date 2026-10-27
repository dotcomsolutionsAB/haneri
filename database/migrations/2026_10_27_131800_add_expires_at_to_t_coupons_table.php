<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_coupons', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('validity');
        });
    }

    public function down(): void
    {
        Schema::table('t_coupons', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
