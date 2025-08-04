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
            $table->string('photo_id')->nullable(); 
            $table->integer('min_qty')->default(1); // Minimum quantity to order
            $table->boolean('is_cod')->default(true); // Supports cash on delivery
            $table->decimal('weight', 8, 2)->nullable(); // Product weight in kg
            $table->text('description')->nullable();
            $table->string('variant_type'); // e.g., 'color', 'size'
            $table->string('variant_value'); // e.g., 'Red', 'XL'
            $table->decimal('discount_price', 8, 2)->nullable();
            $table->decimal('regular_price', 8, 2); // New column for variant price
            $table->decimal('selling_price', 8, 2); // New column for variant price
            $table->decimal('sales_price_vendor', 8, 2);
            $table->float('customer_discount')->nullable();  // Customer Discount value
            $table->float('dealer_discount')->nullable();  // Dealer Discount value
            $table->float('architect_discount')->nullable();  // Architect Discount value
            $table->string('hsn'); // Harmonized System Number
            $table->decimal('regular_tax', 5, 2); // Tax percentage
            $table->decimal('selling_tax', 5, 2); // Tax percentage
            $table->longText('video_url')->nullable();
            $table->longText('product_pdf')->nullable();
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
