<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('3d_file')->nullable()->after('product_pdf');
            $table->unsignedBigInteger('3d_placeholder')->nullable()->after('3d_file');
        });
    }

    public function down(): void
    {
        Schema::table('t_product_variants', function (Blueprint $table) {
            $table->dropColumn(['3d_file', '3d_placeholder']);
        });
    }
};
