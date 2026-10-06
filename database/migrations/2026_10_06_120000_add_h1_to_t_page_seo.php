<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_page_seo', function (Blueprint $table) {
            $table->string('h1')->nullable()->after('page_name');
        });
    }

    public function down(): void
    {
        Schema::table('t_page_seo', function (Blueprint $table) {
            $table->dropColumn('h1');
        });
    }
};
