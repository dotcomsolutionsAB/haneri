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
        Schema::create('t_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // $table->foreignId('parent_id')->nullable()->constrained('t_categories'); // Multi-level support
            $table->string('parent_id')->nullable(); // Multi-level support
            $table->string('photo')->nullable(); // Path to category image
            $table->integer('custom_sort')->default(0); // Sorting priority
            $table->text('description')->nullable(); // Description column
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_categories');
    }
};
