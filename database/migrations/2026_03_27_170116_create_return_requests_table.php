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
         Schema::create('t_return_requests', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('order_id'); // Foreign key to orders table
            $table->unsignedBigInteger('user_id'); // Foreign key to users table
            $table->decimal('amount', 10, 2); // Amount to be refunded
            $table->text('reason'); // Reason for the return
            $table->enum('status', ['initiated', 'accepted', 'declined'])->default('initiated'); // Status of the return request
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_return_requests');
    }
};
