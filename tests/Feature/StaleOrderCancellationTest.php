<?php

namespace Tests\Feature;

use App\Mail\OrderStatusUpdate;
use App\Models\OrderModel;
use App\Models\PaymentModel;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

class StaleOrderCancellationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CheckoutSchema::build();
        Mail::fake();
        putenv('MAIL_FROM_ADDRESS=orders@haneri.test');
        config(['app.cron_token' => 'cron-secret', 'app.pending_order_cancel_hours' => 48]);
    }

    public function test_unpaid_pending_orders_older_than_48_hours_are_cancelled(): void
    {
        $user = $this->customer();
        $stale = $this->order($user->id, 'pending', 'pending', now()->subHours(49));
        $fresh = $this->order($user->id, 'pending', 'pending', now()->subHours(47));
        $paid = $this->order($user->id, 'pending', 'paid', now()->subDays(5));
        $processing = $this->order($user->id, 'processing', 'pending', now()->subDays(5));

        $this->getJson('/api/cron/cancel-stale-orders?token=cron-secret')
            ->assertOk()
            ->assertJsonPath('cancelled', 1)
            ->assertJsonPath('order_ids.0', $stale->id);

        $this->assertSame('cancelled', $stale->fresh()->status);
        $this->assertSame('pending', $fresh->fresh()->status);
        $this->assertSame('pending', $paid->fresh()->status);
        $this->assertSame('processing', $processing->fresh()->status);

        Mail::assertSent(OrderStatusUpdate::class, function (OrderStatusUpdate $mail) use ($user, $stale) {
            return $mail->hasTo($user->email) && (int) $mail->order->id === (int) $stale->id;
        });
    }

    public function test_a_paid_payment_record_keeps_the_order(): void
    {
        $user = $this->customer();
        $order = $this->order($user->id, 'pending', 'pending', now()->subDays(4));
        PaymentModel::create([
            'method' => 'razorpay',
            'amount' => 100,
            'status' => 'paid',
            'order_id' => $order->id,
            'razorpay_order_id' => 'order_test',
            'user' => $user->id,
        ]);

        $this->postJson('/api/cron/cancel-stale-orders', ['token' => 'cron-secret'])
            ->assertOk()
            ->assertJsonPath('cancelled', 0);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_endpoint_rejects_a_missing_token(): void
    {
        $this->getJson('/api/cron/cancel-stale-orders')->assertUnauthorized();
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Manish Sharma',
            'email' => 'manish@example.com',
            'password' => 'secret-pass-123',
            'role' => 'customer',
        ]);
    }

    private function order(int $userId, string $status, string $payment, $createdAt): OrderModel
    {
        $order = OrderModel::create([
            'user_id' => $userId,
            'total_amount' => 8495,
            'status' => $status,
            'payment_status' => $payment,
            'delivery_status' => 'pending',
            'shipping_address' => 'Noida',
            'shipping_charge' => '0',
        ]);
        OrderModel::where('id', $order->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh();
    }
}
