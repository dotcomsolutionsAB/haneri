<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE t_orders MODIFY COLUMN status ENUM('pending', 'processing', 'dispatched', 'completed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Move non-legacy statuses back before shrinking the enum.
        DB::table('t_orders')
            ->whereIn('status', ['processing', 'dispatched'])
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE t_orders MODIFY COLUMN status ENUM('pending', 'completed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending'");
    }
};
