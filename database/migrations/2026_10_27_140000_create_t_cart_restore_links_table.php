<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_cart_restore_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 80)->unique();
            $table->unsignedBigInteger('user_id');
            $table->json('items');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_cart_restore_links');
    }
};
