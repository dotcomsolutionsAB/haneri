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
        Schema::create('t_order_items', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('order_id')->constrained('t_orders'); // Link to orders
            // $table->foreignId('product_id')->constrained('t_products'); // Link to products
            // $table->foreignId('variant_id')->nullable()->constrained('t_product_variants'); // Optional variant
            $table->integer('order_id'); // Link to orders
            $table->integer('product_id'); // Link to products
            $table->integer('variant_id')->nullable(); // Optional variant
            $table->integer('quantity'); // Quantity ordered
            $table->decimal('price', 10, 2); // Final price per item
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_order_items');
    }
};
