<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_orders', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('shipping_charge');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('coupon_code');
        });

        // Backfill discount for historical orders where the coupon reduced total_amount
        // but was never stored (items stay at pre-coupon prices).
        $orders = DB::table('t_orders')->select('id', 'total_amount', 'shipping_charge')->get();
        foreach ($orders as $order) {
            $itemsSum = (float) DB::table('t_order_items')
                ->where('order_id', $order->id)
                ->selectRaw('COALESCE(SUM(price * quantity), 0) as s')
                ->value('s');
            $shipping = (float) ($order->shipping_charge ?? 0);
            $discount = max(0, round($itemsSum + $shipping - (float) $order->total_amount, 2));
            if ($discount > 0) {
                DB::table('t_orders')->where('id', $order->id)->update([
                    'discount_amount' => $discount,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('t_orders', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'discount_amount']);
        });
    }
};
