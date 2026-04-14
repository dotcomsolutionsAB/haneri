<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_blog_faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_id');
            $table->string('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('blog_id')->references('id')->on('t_blogs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_blog_faqs');
    }
};
