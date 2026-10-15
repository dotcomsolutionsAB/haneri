<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_product_variants', function (Blueprint $table) {
            $table->string('sku', 100)->nullable()->after('product_id');
            $table->decimal('length', 8, 2)->nullable()->after('weight');
            $table->decimal('width', 8, 2)->nullable()->after('length');
            $table->decimal('height', 8, 2)->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('t_product_variants', function (Blueprint $table) {
            $table->dropColumn(['sku', 'length', 'width', 'height']);
        });
    }
};
