<?php

namespace Tests\Feature;

use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UploadModel;
use App\Models\User;
use App\Services\DelhiveryService;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

class DelhiveryMpsBookingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CheckoutSchema::build();
    }

    public function test_punch_without_sales_invoice_is_blocked(): void
    {
        $admin = $this->admin();
        $order = $this->order(qty: 2, unitPrice: 8495);

        Sanctum::actingAs($admin);
        $this->postJson('/api/delivery/punch_shipment', [
            'order_id' => $order->id,
            'payload' => $this->punchPayload($order),
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Generate the sales invoice first, then book Delhivery.'
            );
    }

    public function test_mps_payload_shares_order_and_sales_invoice_across_boxes(): void
    {
        $orderData = [
            'customer_name' => 'Santosh Bhoir',
            'customer_address' => 'Taloja, Raigad - 410208, Maharashtra, India',
            'pin' => '410208',
            'city' => 'Raigad',
            'state' => 'Maharashtra',
            'phone' => '9876543210',
            'order_no' => '39-R2',
            'payment_mode' => 'Prepaid',
            'total_amount' => 16990,
            'cod_amount' => 0,
            'order_date' => '2026-11-14',
            'seller_name' => 'Haneri Electricals LLP',
            'seller_address' => 'A48, SECTOR 57 Noida',
            'pickup_name' => 'HANERI HO',
            'pickup_address' => 'A48 SECTOR 57',
            'pickup_pin' => '201301',
            'pickup_city' => 'Noida',
            'pickup_state' => 'Uttar Pradesh',
            'pickup_phone' => '9999999999',
            'shipping_mode' => 'Surface',
        ];

        $packages = [
            [
                'products_description' => 'Fengshui ceiling fan (espresso walnut) (1 of 2)',
                'quantity' => 1,
                'weight' => 5.5,
                'shipment_length' => 60,
                'shipment_width' => 60,
                'shipment_height' => 30,
                'total_amount' => 8495,
                'cod_amount' => 0,
            ],
            [
                'products_description' => 'Fengshui ceiling fan (espresso walnut) (2 of 2)',
                'quantity' => 1,
                'weight' => 5.5,
                'shipment_length' => 60,
                'shipment_width' => 60,
                'shipment_height' => 30,
                'total_amount' => 8495,
                'cod_amount' => 0,
            ],
        ];

        $money = DelhiveryService::allocatePackageAmounts($packages, 16990.0, 0.0);
        $composed = DelhiveryService::composeCreatePayload(
            $orderData,
            $packages,
            $money,
            'HAN-INV-0039',
            ['36348510000486', '36348510000490'],
            '36348510000486'
        );

        $shipments = $composed['payload']['shipments'];
        $this->assertCount(2, $shipments);
        $this->assertSame('39-R2', $shipments[0]['order']);
        $this->assertSame('39-R2', $shipments[1]['order']);
        $this->assertSame('HAN-INV-0039', $shipments[0]['seller_inv']);
        $this->assertSame('HAN-INV-0039', $shipments[1]['seller_inv']);
        $this->assertSame('8495', $shipments[0]['total_amount']);
        $this->assertSame('8495', $shipments[1]['total_amount']);
        $this->assertSame('MPS', $shipments[0]['shipment_type']);
        $this->assertSame('36348510000486', $shipments[0]['master_id']);
        $this->assertSame('36348510000486', $shipments[1]['master_id']);
        $this->assertSame('36348510000490', $shipments[1]['waybill']);
        $this->assertStringNotContainsString('-B', $shipments[0]['order']);
        $this->assertStringNotContainsString('-B', $shipments[0]['seller_inv']);
    }

    public function test_order_detail_exposes_sales_invoice_number(): void
    {
        $admin = $this->admin();
        $order = $this->order(qty: 1, unitPrice: 8495);
        $upload = UploadModel::create([
            'file_path' => 'upload/order_invoice/HAN-INV-0039.pdf',
            'alt_text' => 'HAN-INV-0039',
        ]);
        $order->invoice_id = $upload->id;
        $order->save();

        Sanctum::actingAs($admin);
        $this->getJson('/api/fetch_order_detail/' . $order->id)
            ->assertOk()
            ->assertJsonPath('data.invoice.number', 'HAN-INV-0039');
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

    private function order(int $qty, float $unitPrice): OrderModel
    {
        $customer = User::create([
            'name' => 'Santosh Bhoir',
            'email' => 'santosh@example.com',
            'password' => 'secret-pass-123',
            'mobile' => '9876543210',
            'role' => 'customer',
        ]);
        $product = ProductModel::create([
            'name' => 'Fengshui ceiling fan',
            'slug' => 'fengshui-espresso',
            'is_active' => true,
        ]);
        $order = OrderModel::create([
            'user_id' => $customer->id,
            'total_amount' => $unitPrice * $qty,
            'payment_status' => 'paid',
            'status' => 'completed',
            'shipping_address' => 'Santosh Bhoir, 9876543210, Raigad, Maharashtra, India, D bikaner sweets shop pendhar Near Tatsuno India Pvt Ltd .taloja midc plot no b33.navi mumbai, 410208',
        ]);
        OrderItemModel::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'price' => $unitPrice,
        ]);

        return $order;
    }

    private function punchPayload(OrderModel $order): array
    {
        return [
            'customer_name' => 'Santosh Bhoir',
            'customer_address' => 'Taloja',
            'pin' => '410208',
            'city' => 'Raigad',
            'state' => 'Maharashtra',
            'phone' => '9876543210',
            'payment_mode' => 'Prepaid',
            'total_amount' => (float) $order->total_amount,
            'cod_amount' => 0,
            'products_description' => 'Fengshui',
            'quantity' => 2,
            'weight' => 11,
            'order_date' => now()->toDateString(),
            'pickup_name' => 'HANERI HO',
            'pickup_address' => 'A48',
            'pickup_pin' => '201301',
            'pickup_city' => 'Noida',
            'pickup_state' => 'Uttar Pradesh',
            'pickup_phone' => '9999999999',
            'shipping_mode' => 'surface',
            'packages' => [
                [
                    'products_description' => 'Fan (1 of 2)',
                    'quantity' => 1,
                    'weight' => 5.5,
                    'shipment_length' => 60,
                    'shipment_width' => 60,
                    'shipment_height' => 30,
                    'total_amount' => 8495,
                ],
                [
                    'products_description' => 'Fan (2 of 2)',
                    'quantity' => 1,
                    'weight' => 5.5,
                    'shipment_length' => 60,
                    'shipment_width' => 60,
                    'shipment_height' => 30,
                    'total_amount' => 8495,
                ],
            ],
        ];
    }
}
