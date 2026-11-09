<?php

namespace App\services;

use App\Mail\OrderStatusUpdate;
use App\Models\EmailLog;
use App\Models\OrderModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StaleOrderService
{
    /**
     * Cancel unpaid orders that have stayed pending longer than the configured window.
     *
     * @return array<int, int>
     */
    public function cancelStale(): array
    {
        $hours = max(1, (int) config('app.pending_order_cancel_hours', 48));
        $cutoff = now()->subHours($hours);

        $orders = OrderModel::query()
            ->with('user')
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('delivery_status')->orWhere('delivery_status', 'pending');
            })
            ->whereDoesntHave('payments', function ($query) {
                $query->where('status', 'paid');
            })
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        $cancelled = [];
        foreach ($orders as $order) {
            $order->status = 'cancelled';
            $order->save();
            $cancelled[] = (int) $order->id;
            $this->notify($order);
        }

        return $cancelled;
    }

    private function notify(OrderModel $order): void
    {
        $user = $order->user;
        $email = $user?->email;
        if (! $user || ! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrderStatusUpdate(
                $order,
                $user,
                $order->status,
                $order->payment_status
            ));
            EmailLog::record($email, OrderStatusUpdate::class, 'sent', [
                'recipient_user_id' => $user->id,
                'subject' => 'Order Status Updated - Order #'.$order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stale order cancel email failed: '.$e->getMessage(), [
                'order_id' => $order->id,
            ]);
            EmailLog::record($email, OrderStatusUpdate::class, 'failed', [
                'recipient_user_id' => $user->id,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
