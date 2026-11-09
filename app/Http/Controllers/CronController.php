<?php

namespace App\Http\Controllers;

use App\services\StaleOrderService;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function cancelStaleOrders(Request $request, StaleOrderService $orders)
    {
        $configured = (string) config('app.cron_token');
        $given = (string) ($request->header('X-Cron-Token') ?: $request->input('token', ''));

        if ($configured === '' || ! hash_equals($configured, $given)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $ids = $orders->cancelStale();

        return response()->json([
            'success' => true,
            'cancelled' => count($ids),
            'order_ids' => $ids,
        ]);
    }
}
