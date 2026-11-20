<?php

namespace Tests\Feature;

use App\Mail\OrderStatusUpdate;
use App\Mail\ShipmentMilestoneMail;
use App\Models\OrderModel;
use App\Models\OrderShipment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

class ShipmentMilestoneMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CheckoutSchema::build();
        Mail::fake();
        putenv('MAIL_FROM_ADDRESS=orders@haneri.test');
        config([
            'services.delhivery.webhook_token' => 'delhivery-secret',
            'services.shiprocket.webhook_token' => 'shiprocket-secret',
        ]);
    }

    public function test_in_transit_emails_dispatched_once(): void
    {
        $user = $this->customer();
        $order = $this->order($user->id);
        $this->shipment($order, $user, '36348510000475');

        $this->postJson('/api/delhivery/webhook?token=delhivery-secret', $this->delhivery('36348510000475', 'In Transit', 'UD', 'Noida'))
            ->assertOk()
            ->assertJsonPath('emailed.0', 'dispatched');

        $this->postJson('/api/delhivery/webhook?token=delhivery-secret', $this->delhivery('36348510000475', 'In Transit', 'UD', 'Noida'))
            ->assertOk()
            ->assertJsonPath('emailed', []);

        $this->assertSame('dispatched', $order->fresh()->status);
        Mail::assertSent(ShipmentMilestoneMail::class, 1);
        Mail::assertSent(ShipmentMilestoneMail::class, function (ShipmentMilestoneMail $mail) use ($user) {
            return $mail->milestone === 'dispatched'
                && $mail->hasTo($user->email)
                && $mail->awbs[0]['url'] === 'https://www.delhivery.com/track-v2/package/36348510000475';
        });
        Mail::assertNotSent(OrderStatusUpdate::class);
    }

    public function test_delivered_completes_the_order_and_emails_once(): void
    {
        $user = $this->customer();
        $order = $this->order($user->id);
        $this->shipment($order, $user, '36348510000475');

        $this->postJson('/api/delhivery/webhook?token=delhivery-secret', $this->delhivery('36348510000475', 'In Transit', 'UD'))
            ->assertOk();
        $this->postJson('/api/delhivery/webhook?token=delhivery-secret', $this->delhivery('36348510000475', 'Delivered', 'DL'))
            ->assertOk()
            ->assertJsonPath('emailed.0', 'delivered');
        $this->postJson('/api/delhivery/webhook?token=delhivery-secret', $this->delhivery('36348510000475', 'Delivered', 'DL'))
            ->assertOk()
            ->assertJsonPath('emailed', []);

        $fresh = $order->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('completed', $fresh->delivery_status);
        $this->assertSame('delivered', $fresh->latestShipment->status);

        Mail::assertSent(ShipmentMilestoneMail::class, function (ShipmentMilestoneMail $mail) {
            return $mail->milestone === 'delivered';
        });
        $this->assertSame(1, Mail::sent(ShipmentMilestoneMail::class, function (ShipmentMilestoneMail $mail) {
            return $mail->milestone === 'delivered';
        })->count());
    }

    public function test_manifested_sends_nothing(): void
    {
        $user = $this->customer();
        $order = $this->order($user->id);
        $this->shipment($order, $user, '36348510000475');

        $this->postJson(
            '/api/delhivery/webhook',
            $this->delhivery('36348510000475', 'Manifested', 'UD'),
            ['Authorization' => 'Token delhivery-secret']
        )
            ->assertOk()
            ->assertJsonPath('emailed', []);

        $this->assertSame('processing', $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_wrong_webhook_token_is_rejected(): void
    {
        $this->postJson('/api/delhivery/webhook?token=wrong', $this->delhivery('36348510000475', 'In Transit', 'UD'))
            ->assertUnauthorized();
        $this->postJson('/api/shiprocket/webhook', [
            'awb' => 'AWB100',
            'current_status' => 'IN TRANSIT',
        ])->assertUnauthorized();

        Mail::assertNothingSent();
    }

    public function test_an_old_delivered_shipment_is_recorded_without_an_email(): void
    {
        $user = $this->customer();
        $order = $this->order($user->id);
        $shipment = $this->shipment($order, $user, '36348510000475');
        $shipment->booked_at = now()->subDays(5);
        $shipment->save();

        $this->postJson('/api/delhivery/webhook?token=delhivery-secret', $this->delhivery('36348510000475', 'Delivered', 'DL'))
            ->assertOk()
            ->assertJsonPath('emailed', []);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertContains('delivered', $shipment->fresh()->milestones_sent);
        Mail::assertNothingSent();
    }

    public function test_shiprocket_in_transit_emails_dispatched(): void
    {
        $user = $this->customer();
        $order = $this->order($user->id);
        $this->shipment($order, $user, 'AWB100', 'Shiprocket');

        $this->postJson('/api/shiprocket/webhook', [
            'awb' => 'AWB100',
            'current_status' => 'IN TRANSIT',
            'current_timestamp' => '2026-11-16 08:00:00',
            'scans' => [['location' => 'Delhi', 'date' => '2026-11-16 08:00:00']],
        ], ['x-api-key' => 'shiprocket-secret'])
            ->assertOk()
            ->assertJsonPath('emailed', 'dispatched');

        Mail::assertSent(ShipmentMilestoneMail::class, function (ShipmentMilestoneMail $mail) {
            return $mail->milestone === 'dispatched'
                && $mail->awbs[0]['url'] === 'https://shiprocket.co/tracking/AWB100';
        });
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Nilesh Dere',
            'email' => 'nilesh@example.com',
            'password' => 'secret-pass-123',
            'role' => 'customer',
        ]);
    }

    private function order(int $userId): OrderModel
    {
        return OrderModel::create([
            'user_id' => $userId,
            'total_amount' => 16990,
            'status' => 'processing',
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'shipping_address' => 'Raigad',
            'shipping_charge' => '0',
        ]);
    }

    private function shipment(OrderModel $order, User $user, string $awb, string $courier = 'Delhivery'): OrderShipment
    {
        return OrderShipment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'courier' => $courier,
            'status' => 'booked',
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'shipping_address' => 'Raigad',
            'awb_no' => $awb,
            'booked_at' => now(),
        ]);
    }

    private function delhivery(string $awb, string $status, string $type, string $location = 'Noida'): array
    {
        return [
            'Shipment' => [
                'AWB' => $awb,
                'Status' => [
                    'Status' => $status,
                    'StatusType' => $type,
                    'StatusLocation' => $location,
                    'StatusDateTime' => '2026-11-16T08:00:00',
                ],
            ],
        ];
    }
}
