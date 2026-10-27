<?php

namespace App\services;

use App\Models\OrderModel;
use App\Models\OrderShipment;
use App\services\ShiprocketService as ShiprocketApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ShiprocketBookingService
{
    public function __construct(private ShiprocketApi $shiprocket)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status:int, body:array<string, mixed>}
     */
    public function book(array $input): array
    {
        $order = OrderModel::with(['user', 'items.product'])->find($input['order_id']);
        if (!$order || !$order->user) {
            return $this->response(404, false, 'Order not found.');
        }
        if ($order->items->isEmpty()) {
            return $this->response(422, false, 'Order has no items.');
        }

        $existing = OrderShipment::where('order_id', $order->id)
            ->whereNotIn('status', ['FAILED', 'failed', 'CANCELLED', 'cancelled', 'canceled'])
            ->whereNotNull('awb_no')
            ->latest('id')
            ->first();
        if ($existing) {
            return $this->response(409, false, 'This order already has a shipment. Cancel it before booking another carrier.');
        }

        $packages = array_values($input['packages']);
        $shipTo = $this->parseDestination($order);
        if ($shipTo['error'] !== null) {
            return $this->response(422, false, $shipTo['error']);
        }

        try {
            if (count($packages) > 1) {
                return $this->bookCargo($order, $packages, $input, $shipTo);
            }

            return $this->bookParcel($order, $packages[0], $input, $shipTo);
        } catch (\Throwable $e) {
            return $this->response(422, false, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $shipTo
     * @return array{status:int, body:array<string, mixed>}
     */
    private function bookParcel(OrderModel $order, array $package, array $input, array $shipTo): array
    {
        $creds = $this->shiprocket->credentials();
        $pickup = trim((string) ($input['pickup_location'] ?? $creds['pickup_location']));
        if ($creds['email'] === '' || $creds['password'] === '' || $pickup === '') {
            return $this->response(422, false, 'Save the Shiprocket API email, password, and pickup location in Admin settings first.');
        }

        $items = $this->orderItems($order);
        $payload = [
            'order_id' => 'H' . $order->id . '-' . now()->format('ymdHis'),
            'order_date' => $order->created_at ? Carbon::parse($order->created_at)->format('Y-m-d H:i') : now()->format('Y-m-d H:i'),
            'pickup_location' => $pickup,
            'billing_customer_name' => $shipTo['name'],
            'billing_last_name' => '',
            'billing_address' => $shipTo['address1'],
            'billing_address_2' => $shipTo['address2'],
            'billing_city' => $shipTo['city'],
            'billing_state' => $shipTo['state'],
            'billing_country' => $shipTo['country'],
            'billing_pincode' => $shipTo['pincode'],
            'billing_email' => $shipTo['email'],
            'billing_phone' => $shipTo['phone'],
            'shipping_is_billing' => true,
            'payment_method' => $shipTo['payment_method'],
            'sub_total' => $items['sub_total'],
            'order_items' => $items['rows'],
            'length' => (float) $package['shipment_length'],
            'breadth' => (float) $package['shipment_width'],
            'height' => (float) $package['shipment_height'],
            'weight' => (float) $package['weight'],
        ];

        $created = $this->shiprocket->createOrderAdhoc($payload);
        $shipmentId = data_get($created, 'shipment_id');
        $shiprocketOrderId = data_get($created, 'order_id');
        if (!$shipmentId) {
            return $this->response(422, false, 'Shiprocket created the order but did not return a shipment id.');
        }

        $awbRes = $this->shiprocket->assignAwb((int) $shipmentId);
        $awb = data_get($awbRes, 'response.data.awb_code') ?? data_get($awbRes, 'awb_code');
        $courierName = data_get($awbRes, 'response.data.courier_name');
        $labelRes = $this->shiprocket->generateLabel([(int) $shipmentId]);

        $row = $this->saveShipment($order, $shipTo, [
            'courier' => $courierName ? ('Shiprocket - ' . $courierName) : 'Shiprocket',
            'awb_no' => $awb ? (string) $awb : null,
            'courier_reference' => 'shiprocket_order_id=' . $shiprocketOrderId . ', shipment_id=' . $shipmentId,
            'weight' => (float) $package['weight'],
            'quantity' => (int) ($package['quantity'] ?? 1),
            'products_description' => (string) ($package['products_description'] ?? $items['description']),
            'shipment_length' => (float) $package['shipment_length'],
            'shipment_width' => (float) $package['shipment_width'],
            'shipment_height' => (float) $package['shipment_height'],
            'shipping_mode' => ($input['shipping_mode'] ?? 'surface') === 'air' ? 'air' : 'surface',
            'pickup_name' => $pickup,
            'request_payload' => $payload,
            'response_payload' => [
                'created_response' => $created,
                'awb_response' => $awbRes,
                'label_response' => $labelRes,
            ],
        ]);

        return $this->response(200, true, $awb
            ? 'Shipment booked with Shiprocket. AWB ' . $awb . '.'
            : 'Shipment created in Shiprocket. AWB was not assigned yet.', [
            'awb' => $awb,
            'carrier' => 'shiprocket',
            'shipments_table_id' => $row->id,
            'label_url' => data_get($labelRes, 'label_url'),
            'packages' => $awb ? [['waybill' => (string) $awb]] : [],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $shipTo
     * @return array{status:int, body:array<string, mixed>}
     */
    private function bookCargo(OrderModel $order, array $packages, array $input, array $shipTo): array
    {
        $creds = $this->shiprocket->credentials();
        if ($creds['cargo_access_token'] === '' || $creds['cargo_refresh_token'] === '' || $creds['cargo_client_id'] === '') {
            return $this->response(422, false, 'This order has more than one box. Save the Shiprocket Cargo access token, refresh token, and client id in Admin settings.');
        }

        $pickupPin = preg_replace('/\D+/', '', (string) ($input['pickup_pin'] ?? '')) ?? '';
        $pickupAddress = trim((string) ($input['pickup_address'] ?? ''));
        $pickupCity = trim((string) ($input['pickup_city'] ?? ''));
        $pickupState = trim((string) ($input['pickup_state'] ?? ''));
        if ($pickupPin === '' || $pickupAddress === '' || $pickupCity === '' || $pickupState === '') {
            return $this->response(422, false, 'Choose a pickup warehouse before booking Shiprocket Cargo.');
        }

        $seller = \App\Http\Controllers\SettingController::sellerDetails();
        $items = $this->orderItems($order);
        $mode = ($input['shipping_mode'] ?? 'surface') === 'air' ? 'air' : 'surface';
        $units = [];
        $weight = 0.0;
        foreach ($packages as $package) {
            $boxWeight = (float) $package['weight'];
            $weight += $boxWeight;
            $units[] = [
                'units' => max(1, (int) ($package['quantity'] ?? 1)),
                'weight' => $boxWeight,
                'length' => (float) $package['shipment_length'],
                'width' => (float) $package['shipment_width'],
                'height' => (float) $package['shipment_height'],
                'display_in' => 'cm',
            ];
        }

        $clientOrderId = 'H' . $order->id . '-' . now()->format('ymdHis');
        $payload = [
            'client_id' => $creds['cargo_client_id'],
            'client_order_id' => $clientOrderId,
            'no_of_packages' => count($packages),
            'approx_weight' => round($weight, 3),
            'invoice_value' => round((float) ($order->total_amount ?? $items['sub_total']), 2),
            'invoice_number' => (string) ($order->invoice_id ?: $clientOrderId),
            'invoice_date' => now()->format('Y-m-d'),
            'mode_name' => $mode,
            'is_insured' => false,
            'is_to_pay_sender' => false,
            'source' => 'API',
            'packaging_unit_details' => $units,
            'source_warehouse_name' => trim((string) ($input['pickup_name'] ?? $seller['name'])),
            'source_address_line1' => $pickupAddress,
            'source_pincode' => $pickupPin,
            'source_city' => $pickupCity,
            'source_state' => $pickupState,
            'sender_contact_person_name' => $seller['name'] !== '' ? $seller['name'] : 'Haneri',
            'sender_contact_person_email' => $creds['email'] !== '' ? $creds['email'] : 'info@haneri.in',
            'sender_contact_person_contact_no' => preg_replace('/\D+/', '', (string) ($input['pickup_phone'] ?? '')) ?: '0000000000',
            'destination_warehouse_name' => $shipTo['name'],
            'destination_address_line1' => trim($shipTo['address1'] . ' ' . $shipTo['address2']),
            'destination_pincode' => $shipTo['pincode'],
            'destination_city' => $shipTo['city'],
            'destination_state' => $shipTo['state'],
            'recipient_contact_person_name' => $shipTo['name'],
            'recipient_contact_person_email' => $shipTo['email'],
            'recipient_contact_person_contact_no' => $shipTo['phone'],
        ];

        $created = $this->cargoPost($creds, 'shipment/create/', $payload);
        $awb = (string) (data_get($created, 'awb')
            ?? data_get($created, 'awb_code')
            ?? data_get($created, 'waybill')
            ?? data_get($created, 'lr_number')
            ?? data_get($created, 'data.awb')
            ?? data_get($created, 'data.waybill')
            ?? '');
        $children = data_get($created, 'packages') ?? data_get($created, 'data.packages') ?? [];
        $waybills = [];
        if (is_array($children)) {
            foreach ($children as $child) {
                $childAwb = trim((string) (data_get($child, 'awb') ?? data_get($child, 'waybill') ?? ''));
                if ($childAwb !== '') {
                    $waybills[] = $childAwb;
                }
            }
        }
        if ($awb === '' && $waybills !== []) {
            $awb = $waybills[0];
        }
        if ($awb === '') {
            return $this->response(422, false, 'Shiprocket Cargo did not return an AWB. ' . ShiprocketApi::errorText($created, ''));
        }
        if ($waybills === []) {
            $waybills = [$awb];
        }

        $row = $this->saveShipment($order, $shipTo, [
            'courier' => 'Shiprocket Cargo',
            'awb_no' => implode(', ', $waybills),
            'courier_reference' => $awb,
            'weight' => $weight,
            'quantity' => count($packages),
            'products_description' => $items['description'],
            'shipment_length' => (float) $packages[0]['shipment_length'],
            'shipment_width' => (float) $packages[0]['shipment_width'],
            'shipment_height' => (float) $packages[0]['shipment_height'],
            'shipping_mode' => $mode,
            'pickup_name' => $payload['source_warehouse_name'],
            'pickup_address' => $pickupAddress,
            'pickup_pin' => $pickupPin,
            'pickup_city' => $pickupCity,
            'pickup_state' => $pickupState,
            'pickup_phone' => $payload['sender_contact_person_contact_no'],
            'request_payload' => $payload,
            'response_payload' => ['created_response' => $created],
        ]);

        return $this->response(200, true, 'Shiprocket Cargo booked. Master AWB ' . $awb . ' · ' . count($waybills) . ' packages.', [
            'awb' => $awb,
            'master_waybill' => $awb,
            'carrier' => 'shiprocket_cargo',
            'shipments_table_id' => $row->id,
            'packages' => array_map(fn ($waybill) => ['waybill' => $waybill], $waybills),
        ]);
    }

    /**
     * @param  array{cargo_base_url:string,cargo_access_token:string,cargo_refresh_token:string}  $creds
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cargoPost(array $creds, string $path, array $payload): array
    {
        $token = $this->cargoToken($creds);
        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->post($creds['cargo_base_url'] . '/' . ltrim($path, '/'), $payload);

        $json = $response->json();
        if (!$response->successful()) {
            throw new \RuntimeException(ShiprocketApi::errorText(is_array($json) ? $json : null, $response->body()));
        }

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array{cargo_base_url:string,cargo_access_token:string,cargo_refresh_token:string}  $creds
     */
    private function cargoToken(array $creds): string
    {
        $key = ShiprocketApi::cargoTokenCacheKey($creds['cargo_access_token'], $creds['cargo_refresh_token']);

        return Cache::remember($key, now()->addHours(12), function () use ($creds) {
            $response = Http::acceptJson()->asJson()->timeout(30)->post($creds['cargo_base_url'] . '/token/', [
                'username' => $creds['cargo_access_token'],
                'password' => $creds['cargo_refresh_token'],
            ]);
            $json = $response->json();
            if (!$response->successful()) {
                throw new \RuntimeException(ShiprocketApi::errorText(is_array($json) ? $json : null, $response->body()));
            }
            $token = is_array($json) ? ($json['access'] ?? $json['token'] ?? null) : null;
            if (!is_string($token) || $token === '') {
                throw new \RuntimeException('Shiprocket Cargo did not return an access token.');
            }

            return $token;
        });
    }

    /**
     * @param  array<string, mixed>  $shipTo
     * @param  array<string, mixed>  $fields
     */
    private function saveShipment(OrderModel $order, array $shipTo, array $fields): OrderShipment
    {
        return DB::transaction(function () use ($order, $shipTo, $fields) {
            return OrderShipment::create(array_merge([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'status' => 'booked',
                'customer_name' => $shipTo['name'],
                'customer_phone' => $shipTo['phone'],
                'customer_email' => $shipTo['email'],
                'shipping_address' => trim($shipTo['address1'] . ' ' . $shipTo['address2']),
                'shipping_pin' => $shipTo['pincode'],
                'shipping_city' => $shipTo['city'],
                'shipping_state' => $shipTo['state'],
                'payment_mode' => $shipTo['payment_method'],
                'total_amount' => (float) ($order->total_amount ?? 0),
                'cod_amount' => $shipTo['payment_method'] === 'COD' ? (float) ($order->total_amount ?? 0) : 0,
                'booked_at' => now(),
                'error_message' => null,
            ], $fields));
        });
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, sub_total: float, description: string}
     */
    private function orderItems(OrderModel $order): array
    {
        $rows = [];
        $subTotal = 0.0;
        $names = [];
        foreach ($order->items as $item) {
            $name = $item->product->name ?? ('Product ' . $item->product_id);
            $sku = $item->product->sku ?? ('SKU-' . $item->product_id);
            $units = max(1, (int) $item->quantity);
            $price = (float) $item->price;
            $rows[] = [
                'name' => $name,
                'sku' => (string) $sku,
                'units' => $units,
                'selling_price' => $price,
            ];
            $subTotal += $units * $price;
            $names[] = $name;
        }

        return [
            'rows' => $rows,
            'sub_total' => round($subTotal, 2),
            'description' => implode(', ', $names),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDestination(OrderModel $order): array
    {
        $name = $order->user->name ?? 'Customer';
        $email = $order->user->email ?? 'info@haneri.in';
        $phone = (string) ($order->user->mobile ?? '');
        $address1 = '';
        $address2 = '';
        $city = '';
        $state = '';
        $pincode = '';
        $country = 'India';
        $raw = $order->shipping_address;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (is_array($decoded)) {
            $name = $decoded['name'] ?? $name;
            $phone = (string) ($decoded['phone'] ?? $decoded['mobile'] ?? $phone);
            $address1 = (string) ($decoded['address'] ?? $decoded['address1'] ?? $decoded['line1'] ?? '');
            $address2 = (string) ($decoded['address2'] ?? $decoded['line2'] ?? '');
            $city = (string) ($decoded['city'] ?? '');
            $state = (string) ($decoded['state'] ?? '');
            $country = (string) ($decoded['country'] ?? 'India');
            $pincode = (string) ($decoded['pincode'] ?? $decoded['pin'] ?? '');
        } else {
            $parts = array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn ($part) => $part !== ''));
            if (count($parts) >= 6) {
                $name = $parts[0] ?: $name;
                $phone = $parts[1] ?: $phone;
                $city = $parts[2] ?? '';
                $state = $parts[3] ?? '';
                $country = $parts[4] ?? 'India';
                $pincode = $parts[5] ?? '';
                $address1 = trim(implode(', ', array_slice($parts, 6))) ?: $city;
            } else {
                $address1 = trim((string) $raw);
            }
        }

        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        $pincode = preg_replace('/\D+/', '', $pincode) ?? '';
        $error = null;
        if ($address1 === '' || $city === '' || $state === '' || $pincode === '' || $phone === '') {
            $error = 'Shipping address is incomplete. It needs a name, phone, address, city, state, and pincode.';
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'pincode' => $pincode,
            'payment_method' => $order->payment_status === 'paid' ? 'Prepaid' : 'COD',
            'error' => $error,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, body:array<string, mixed>}
     */
    private function response(int $status, bool $success, string $message, array $data = []): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => $success,
                'message' => $message,
                'data' => $data,
            ],
        ];
    }
}
