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
        Schema::create('t_product_variants', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('product_id')->constrained('t_products');
            $table->integer('product_id');
            $table->string('variant_type'); // e.g., 'color', 'size'
            $table->string('variant_value'); // e.g., 'Red', 'XL'
            $table->decimal('price', 8, 2); // New column for variant price
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_product_variants');
    }
};
