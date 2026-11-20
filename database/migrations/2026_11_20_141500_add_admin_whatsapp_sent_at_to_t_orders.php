<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('t_orders', 'admin_whatsapp_sent_at')) {
                $table->timestamp('admin_whatsapp_sent_at')->nullable()->after('mail_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_orders', function (Blueprint $table) {
            if (Schema::hasColumn('t_orders', 'admin_whatsapp_sent_at')) {
                $table->dropColumn('admin_whatsapp_sent_at');
            }
        });
    }
};
