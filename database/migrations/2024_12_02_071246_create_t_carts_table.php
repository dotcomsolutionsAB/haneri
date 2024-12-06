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
        Schema::create('t_carts', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('user_id')->constrained('users'); // User ID
            // $table->foreignId('product_id')->constrained('t_products'); // Product ID
            // $table->foreignId('variant_id')->nullable()->constrained('t_product_variants'); // Optional variant ID
            $table->string('user_id'); // User ID
            $table->integer('product_id'); // Product ID
            $table->integer('variant_id')->nullable(); // Optional variant ID
            $table->integer('quantity')->default(1); // Quantity added to cart
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_carts');
    }
};
