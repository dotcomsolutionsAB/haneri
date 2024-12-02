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
        Schema::create('t_product_features', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('product_id')->constrained('t_products'); // Links to products table
            $table->integer('product_id'); // Links to products table
            $table->string('feature_name'); // e.g., 'Material', 'Warranty' $table->string('feature_value'); // e.g., 'Metal', '2 years' 
            $table->boolean('is_filterable')->default(false); // New column for filterable feature
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_product_features');
    }
};
