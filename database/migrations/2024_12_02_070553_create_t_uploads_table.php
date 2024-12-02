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
        Schema::create('t_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_path'); // Path to the file
            $table->string('type'); // File type (image, PDF, video, etc.)
            $table->bigInteger('size')->nullable(); // File size in bytes
            $table->string('alt_text')->nullable(); // Alternative text for images
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_uploads');
    }
};
