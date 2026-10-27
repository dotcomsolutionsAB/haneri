<?php

namespace Tests\Feature;

use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderShipment;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

class ShiprocketBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CheckoutSchema::build();
    }

    public function test_shiprocket_settings_keep_secrets_off_the_response(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/settings/shiprocket', [
            'email' => 'api@haneri.in',
            'password' => 'secret-pass',
            'pickup_location' => 'Haneri Warehouse',
            'cargo_client_id' => '88',
            'cargo_access_token' => 'access-token',
            'cargo_refresh_token' => 'refresh-token',
        ])->assertOk()
            ->assertJsonPath('data.email', 'api@haneri.in')
            ->assertJsonPath('data.password_set', true)
            ->assertJsonPath('data.cargo_configured', true);

        $shown = $this->getJson('/api/settings/shiprocket')->assertOk();
        $this->assertStringNotContainsString('secret-pass', $shown->getContent());
        $this->assertStringNotContainsString('access-token', $shown->getContent());
        $this->assertStringNotContainsString('refresh-token', $shown->getContent());

        $this->putJson('/api/settings/shiprocket', [
            'email' => 'api@haneri.in',
            'password' => '',
            'pickup_location' => 'Haneri Warehouse',
            'cargo_client_id' => '88',
            'cargo_access_token' => '',
            'cargo_refresh_token' => '',
        ])->assertOk()->assertJsonPath('data.cargo_configured', true);
    }

    public function test_one_box_books_a_shiprocket_courier(): void
    {
        $admin = $this->saveCredentials();
        $order = $this->order();
        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response(['token' => 'parcel-token']),
            'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc' => Http::response([
                'order_id' => 55,
                'shipment_id' => 77,
                'status' => 'NEW',
            ]),
            'https://apiv2.shiprocket.in/v1/external/courier/assign/awb' => Http::response([
                'response' => ['data' => ['awb_code' => 'AWB100', 'courier_name' => 'BlueDart']],
            ]),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/label' => Http::response([
                'label_url' => 'https://labels.example/1.pdf',
            ]),
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/shiprocket/book', [
            'order_id' => $order->id,
            'pickup_location' => 'Haneri Warehouse',
            'shipping_mode' => 'surface',
            'packages' => [$this->box('JadeSpin')],
        ])->assertOk()
            ->assertJsonPath('data.awb', 'AWB100')
            ->assertJsonPath('data.carrier', 'shiprocket');

        $shipment = OrderShipment::where('order_id', $order->id)->first();
        $this->assertSame('AWB100', $shipment->awb_no);
        $this->assertStringContainsString('Shiprocket', $shipment->courier);
        $this->assertStringNotContainsString('Cargo', $shipment->courier);
    }

    public function test_multiple_boxes_book_shiprocket_cargo(): void
    {
        $admin = $this->saveCredentials();
        $order = $this->order();
        Http::fake([
            'https://api.rocketbox.in/api/token/' => Http::response(['access' => 'cargo-token']),
            'https://api.rocketbox.in/api/shipment/create/' => Http::response([
                'awb' => 'CARGO100',
                'packages' => [
                    ['awb' => 'CARGO100'],
                    ['awb' => 'CARGO101'],
                ],
            ]),
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/shiprocket/book', [
            'order_id' => $order->id,
            'pickup_location' => 'Haneri Warehouse',
            'shipping_mode' => 'surface',
            'pickup_name' => 'Haneri Warehouse',
            'pickup_address' => '12 Industrial Area',
            'pickup_pin' => '560076',
            'pickup_city' => 'Bengaluru',
            'pickup_state' => 'Karnataka',
            'pickup_phone' => '9800000000',
            'packages' => [$this->box('JadeSpin'), $this->box('Aero')],
        ])->assertOk()
            ->assertJsonPath('data.carrier', 'shiprocket_cargo')
            ->assertJsonPath('data.master_waybill', 'CARGO100');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.rocketbox.in/api/shipment/create/'
                && count($request['packaging_unit_details']) === 2;
        });
        $this->assertSame('Shiprocket Cargo', OrderShipment::where('order_id', $order->id)->value('courier'));
        $this->assertSame('CARGO100, CARGO101', OrderShipment::where('order_id', $order->id)->value('awb_no'));
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@haneri.in',
            'password' => 'secret-pass-123',
            'role' => 'admin',
        ]);
    }

    private function saveCredentials(): User
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->putJson('/api/settings/shiprocket', [
            'email' => 'api@haneri.in',
            'password' => 'secret-pass',
            'pickup_location' => 'Haneri Warehouse',
            'cargo_client_id' => '88',
            'cargo_access_token' => 'access-token',
            'cargo_refresh_token' => 'refresh-token',
        ])->assertOk();

        return $admin;
    }

    private function order(): OrderModel
    {
        $customer = User::create([
            'name' => 'Ramesh Kumar',
            'email' => 'ramesh@example.com',
            'password' => 'secret-pass-123',
            'mobile' => '9876543210',
            'role' => 'customer',
        ]);
        $product = ProductModel::create([
            'name' => 'JadeSpin Ceiling Fan',
            'slug' => 'jadespin',
            'is_active' => true,
        ]);
        $order = OrderModel::create([
            'user_id' => $customer->id,
            'total_amount' => 7745,
            'payment_status' => 'paid',
            'status' => 'processing',
            'shipping_address' => json_encode([
                'name' => 'Ramesh Kumar',
                'phone' => '9876543210',
                'address' => '12 MG Road',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'country' => 'India',
            ]),
        ]);
        OrderItemModel::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 7745,
        ]);

        return $order;
    }

    private function box(string $name): array
    {
        return [
            'products_description' => $name,
            'quantity' => 1,
            'weight' => 6.5,
            'shipment_length' => 40,
            'shipment_width' => 40,
            'shipment_height' => 30,
        ];
    }
}
