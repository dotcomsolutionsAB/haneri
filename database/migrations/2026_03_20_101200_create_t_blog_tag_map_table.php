<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_blog_tag_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blog_id');
            $table->unsignedBigInteger('blog_tag_id');
            $table->timestamps();

            $table->unique(['blog_id', 'blog_tag_id']);
            $table->foreign('blog_id')->references('id')->on('t_blogs')->onDelete('cascade');
            $table->foreign('blog_tag_id')->references('id')->on('t_blog_tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_blog_tag_map');
    }
};
