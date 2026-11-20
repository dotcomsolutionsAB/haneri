<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_order_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('t_order_shipments', 'carrier_status')) {
                $table->string('carrier_status')->nullable()->after('status');
            }
            if (! Schema::hasColumn('t_order_shipments', 'carrier_status_at')) {
                $table->timestamp('carrier_status_at')->nullable()->after('carrier_status');
            }
            if (! Schema::hasColumn('t_order_shipments', 'status_location')) {
                $table->string('status_location')->nullable()->after('carrier_status_at');
            }
            if (! Schema::hasColumn('t_order_shipments', 'milestones_sent')) {
                $table->json('milestones_sent')->nullable()->after('status_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_order_shipments', function (Blueprint $table) {
            foreach (['milestones_sent', 'status_location', 'carrier_status_at', 'carrier_status'] as $column) {
                if (Schema::hasColumn('t_order_shipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
