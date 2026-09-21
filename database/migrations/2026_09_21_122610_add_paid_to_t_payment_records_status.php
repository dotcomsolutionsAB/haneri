<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow payment records to be marked paid after Razorpay capture.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE t_payment_records MODIFY COLUMN status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE t_payment_records MODIFY COLUMN status ENUM('pending', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
