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
        Schema::create('t_products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // SEO-friendly URL
            $table->string('name');
            $table->integer('brand_id');
            $table->integer('category_id');
            $table->text('description');
            $table->enum('type', ['simple', 'variable'])->default('simple'); // Order status
            $table->boolean('is_active')->default(true); // Active or inactive status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_products');
    }
};
