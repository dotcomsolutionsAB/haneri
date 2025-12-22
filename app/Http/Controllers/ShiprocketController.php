<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\services\ShiprocketService;
use App\Models\OrderModel;
use App\Models\OrderShipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ShiprocketController extends Controller
{
    public function createShipment(Request $request, ShiprocketService $shiprocket)
    {
        // ✅ Make keys "present" always (Shiprocket throws validation.present if missing)
        $request->merge([
            'shipping_is_billing' => $request->input('shipping_is_billing', true),
            'billing_last_name'   => $request->input('billing_last_name', ''),

            // shiprocket requires these
            'length'  => $request->input('length', 10),
            'breadth' => $request->input('breadth', 10),
            'height'  => $request->input('height', 10),
            'weight'  => $request->input('weight', 0.5),
        ]);

        $validator = Validator::make($request->all(), [
            'order_id' => ['required', 'string', 'max:50'],
            'order_date' => ['required', 'date'],
            'billing_customer_name' => ['required', 'string', 'max:255'],

            'billing_last_name' => ['present', 'nullable', 'string', 'max:255'],

            'billing_address' => ['required', 'string', 'max:500'],
            'billing_city' => ['required', 'string', 'max:100'],
            'billing_state' => ['required', 'string', 'max:100'],
            'billing_country' => ['required', 'string', 'max:100'],
            'billing_pincode' => ['required', 'string', 'max:10'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'max:20'],

            'shipping_is_billing' => ['present', 'boolean'],

            'payment_method' => ['required', 'in:COD,Prepaid'],
            'sub_total' => ['required', 'numeric', 'min:0'],

            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.name' => ['required', 'string'],
            'order_items.*.sku' => ['required', 'string'],
            'order_items.*.units' => ['required', 'integer', 'min:1'],
            'order_items.*.selling_price' => ['required', 'numeric', 'min:0'],

            // ✅ shiprocket required
            'length'  => ['required','numeric','min:1'],
            'breadth' => ['required','numeric','min:1'],
            'height'  => ['required','numeric','min:1'],
            'weight'  => ['required','numeric','min:0.1'],

            'courier_id' => ['nullable','integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'data' => $validator->errors(),
            ], 422);
        }

        $payload = $request->all();

        // Must exist in Shiprocket panel
        $payload['pickup_location'] = (string) config('services.shiprocket.pickup_location');

        // ✅ enforce min values (Shiprocket rejects 0 / missing)
        $payload['length']  = max((float)$payload['length'],  1.0);
        $payload['breadth'] = max((float)$payload['breadth'], 1.0);
        $payload['height']  = max((float)$payload['height'],  1.0);
        $payload['weight']  = max((float)$payload['weight'],  0.1);
        $payload['billing_last_name'] = (string)($payload['billing_last_name'] ?? '');

        // 1) Create order
        $created = $shiprocket->createOrderAdhoc($payload);

        $shipmentId = data_get($created, 'shipment_id');
        $orderIdSr  = data_get($created, 'order_id');

        if (!$shipmentId) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Shiprocket order created but shipment_id missing.',
                'data' => $created,
            ], 500);
        }

        // 2) Assign AWB (optionally pass courier_id)
        $courierId = $request->input('courier_id');
        $awbRes = $shiprocket->assignAwb((int)$shipmentId, $courierId ? (int)$courierId : null);

        $awb = data_get($awbRes, 'awb_code') ?? data_get($awbRes, 'awb');

        // 3) Generate Label
        $labelRes = $shiprocket->generateLabel([(int)$shipmentId]);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Shipment created in Shiprocket.',
            'data' => [
                'shiprocket_order_id' => $orderIdSr,
                'shipment_id' => (int)$shipmentId,
                'awb' => $awb,
                'created_response' => $created,
                'awb_response' => $awbRes,
                'label_response' => $labelRes,
            ],
        ]);
    }

    public function trackAwb($awb, ShiprocketService $shiprocket)
    {
        $data = $shiprocket->trackByAwb((string)$awb);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Tracking fetched.',
            'data' => $data,
        ]);
    }

    // create ship order by order id
    public function punchOrderById(Request $request, $order_id, ShiprocketService $shiprocket)
    {
        // Optional dimensions from API call
        $request->merge([
            'length'  => $request->input('length', 10),
            'breadth' => $request->input('breadth', 10),
            'height'  => $request->input('height', 10),
            'weight'  => $request->input('weight', 0.5),
            'courier_id' => $request->input('courier_id'),
        ]);

        $v = Validator::make($request->all(), [
            'length'  => ['required','numeric','min:1'],
            'breadth' => ['required','numeric','min:1'],
            'height'  => ['required','numeric','min:1'],
            'weight'  => ['required','numeric','min:0.1'],
            'courier_id' => ['nullable','integer'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'data' => $v->errors(),
            ], 422);
        }

        // 1) Fetch order + user + items + product
        $order = OrderModel::with(['user', 'items.product'])
            ->where('id', $order_id)
            ->first();

        if (!$order) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => "Order not found for id {$order_id}.",
                'data' => [],
            ], 404);
        }

        if (!$order->user) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => "User not found for this order.",
                'data' => [],
            ], 404);
        }

        if ($order->items->isEmpty()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => "Order has no items.",
                'data' => [],
            ], 422);
        }
        // ✅ Prevent duplicate Shiprocket shipment creation for same order
        $existing = OrderShipment::where('order_id', $order->id)
            ->where(function ($q) {
                $q->where('courier', 'like', '%Shiprocket%')
                ->orWhere('courier_reference', 'like', '%shiprocket_order_id=%');
            })
            ->whereNotIn('status', ['FAILED', 'CANCELLED'])
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'code' => 409,
                'success' => false,
                'message' => 'Shiprocket shipment already created for this order.',
                'data' => [
                    'shipments_table_id' => $existing->id,
                    'awb_no' => $existing->awb_no,
                    'courier_reference' => $existing->courier_reference,
                ],
            ], 409);
        }


        // 2) Parse shipping_address from t_orders.shipping_address
        $shippingRaw = $order->shipping_address;

        // Try JSON first
        $shippingArr = null;
        if (is_string($shippingRaw)) {
            $decoded = json_decode($shippingRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $shippingArr = $decoded;
            }
        }

        // defaults from user table
        $custName  = $order->user->name ?? 'Customer';
        $custEmail = $order->user->email ?? 'test@example.com';
        $custPhone = $order->user->mobile ?? ($order->user->phone ?? '');

        // Variables we must fill
        $address1 = '';
        $address2 = '';
        $city     = '';
        $state    = '';
        $pincode  = '';
        $country  = 'India';
        $phone    = $custPhone;

        // ✅ Case A: shipping stored as JSON
        if (is_array($shippingArr)) {

            $custName = $shippingArr['name'] ?? $custName;
            $phone    = $shippingArr['phone'] ?? $shippingArr['mobile'] ?? $phone;

            $address1 = $shippingArr['address'] ?? $shippingArr['address1'] ?? $shippingArr['line1'] ?? '';
            $address2 = $shippingArr['address2'] ?? $shippingArr['line2'] ?? '';

            $city     = $shippingArr['city'] ?? '';
            $state    = $shippingArr['state'] ?? '';
            $country  = $shippingArr['country'] ?? 'India';
            $pincode  = $shippingArr['pincode'] ?? $shippingArr['pin'] ?? '';

        // ✅ Case B: shipping stored as comma-separated string
        } else {

            $raw = trim((string)$shippingRaw);

            // Example format:
            // Name, Mobile, City, State, Country, Pincode, Address1 (rest...)
            $parts = array_map('trim', explode(',', $raw));
            $parts = array_values(array_filter($parts, fn($x) => $x !== ''));

            if (count($parts) >= 6) {
                $custName = $parts[0] ?? $custName;
                $phone    = $parts[1] ?? $phone;
                $city     = $parts[2] ?? '';
                $state    = $parts[3] ?? '';
                $country  = $parts[4] ?? 'India';
                $pincode  = $parts[5] ?? '';

                // Everything after pincode is address
                $addrParts = array_slice($parts, 6);
                $address1  = trim(implode(', ', $addrParts));

                // fallback if no address given after pincode
                if ($address1 === '') {
                    $address1 = $city; // at least keep something
                }
            } else {
                // fallback: treat whole string as address only
                $address1 = $raw;
            }
        }

        // ✅ Basic cleanup
        $phone = preg_replace('/\D+/', '', (string)$phone); // keep digits only
        $pincode = preg_replace('/\D+/', '', (string)$pincode);

        // ✅ Validate required fields now
        if (trim($address1) === '' || trim($city) === '' || trim($state) === '' || trim($pincode) === '' || trim($phone) === '') {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Shipping address is incomplete in t_orders.shipping_address. Required: name, phone, city, state, pincode, address.',
                'data' => [
                    'shipping_address_raw' => $shippingRaw,
                    'parsed' => [
                        'name' => $custName,
                        'phone' => $phone,
                        'address1' => $address1,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'pincode' => $pincode,
                    ],
                ],
            ], 422);
        }

        // 3) Build Shiprocket items
        $orderItems = [];
        $subTotal = 0;

        foreach ($order->items as $it) {
            $name = $it->product->item_name ?? $it->product->name ?? ('Product ' . $it->product_id);
            $sku  = $it->product->sku ?? $it->product->product_code ?? ('SKU-' . $it->product_id);

            $units = (int) ($it->quantity ?? 1);
            $price = (float) ($it->price ?? 0);

            $orderItems[] = [
                'name' => $name,
                'sku' => (string)$sku,
                'units' => $units,
                'selling_price' => $price,
            ];

            $subTotal += ($units * $price);
        }

        // 4) Decide payment method
        // Adjust these conditions as per your DB values
        $paymentMethod = ($order->payment_status === 'paid') ? 'Prepaid' : 'COD';
        $codAmount = ($paymentMethod === 'COD') ? (float)($order->total_amount ?? $subTotal) : 0;

        // 5) Shiprocket payload
        $channelOrderId = $order->id; // If you have so_no, use that instead

        $payload = [
            'order_id' => $channelOrderId,
            'order_date' => $order->created_at ? Carbon::parse($order->created_at)->format('Y-m-d') : now()->format('Y-m-d'),
            'pickup_location' => (string) config('services.shiprocket.pickup_location'),

            'billing_customer_name' => $custName,
            'billing_last_name' => '',

            'billing_address' => $address1,
            'billing_address_2' => $address2,
            'billing_city' => $city,
            'billing_state' => $state,
            'billing_country' => $country,
            'billing_pincode' => (string)$pincode,
            'billing_email' => $custEmail,
            'billing_phone' => (string)$phone,

            'shipping_is_billing' => true,

            'payment_method' => $paymentMethod,
            'sub_total' => round($subTotal, 2),
            'order_items' => $orderItems,

            'length'  => (float)$request->input('length'),
            'breadth' => (float)$request->input('breadth'),
            'height'  => (float)$request->input('height'),
            'weight'  => (float)$request->input('weight'),
        ];

        $courierId = $request->input('courier_id');

        DB::beginTransaction();
        try {
            // 6) Create order on Shiprocket
            $created = $shiprocket->createOrderAdhoc($payload);
            $srStatus = strtoupper((string) data_get($created, 'status', 'NEW'));

            // Map Shiprocket status -> your DB allowed statuses
            $mappedStatus = match ($srStatus) {
                'NEW' => 'booked',              // ✅ punch success = booked
                'CANCELED', 'CANCELLED' => 'cancelled',
                'DELIVERED' => 'delivered',
                'IN TRANSIT', 'IN_TRANSIT' => 'in_transit',
                default => 'booked',
            };
            $shipmentId = data_get($created, 'shipment_id');
            $shiprocketOrderId = data_get($created, 'order_id');

            if (!$shipmentId) {
                DB::rollBack();
                return response()->json([
                    'code' => 500,
                    'success' => false,
                    'message' => 'Shiprocket created response but shipment_id missing.',
                    'data' => $created,
                ], 500);
            }

            // 7) Assign AWB
            $awbRes = $shiprocket->assignAwb((int)$shipmentId, $courierId ? (int)$courierId : null);

            // ✅ Correct AWB extraction (your response structure)
            $awb = data_get($awbRes, 'response.data.awb_code') ?? data_get($awbRes, 'awb_code') ?? null;
            $courierName = data_get($awbRes, 'response.data.courier_name') ?? null;
            $courierCompanyId = data_get($awbRes, 'response.data.courier_company_id') ?? null;

            // 8) Generate Label
            $labelRes = $shiprocket->generateLabel([(int)$shipmentId]);
            $labelUrl = data_get($labelRes, 'label_url');

            // 9) Save into t_order_shipments
            $shippedBy = data_get($awbRes, 'response.data.shipped_by', []);
            $pickupId = 0; // set correctly later

            $shipmentRow = OrderShipment::create([
                'order_id' => $order->id,
                'user_id'  => $order->user_id,

                'courier' => $courierName ?: 'Shiprocket',
                'status' => $mappedStatus,
                'pickup_location_id' => $pickupId,

                'customer_name'  => $custName,
                'customer_phone' => (string)$phone,
                'customer_email' => $custEmail,

                'shipping_address' => trim($address1 . ' ' . $address2),
                'shipping_pin'   => (string)$pincode,
                'shipping_city'  => (string)$city,
                'shipping_state' => (string)$state,

                'payment_mode' => $paymentMethod,
                'total_amount' => (float)($order->total_amount ?? $subTotal),
                'cod_amount'   => (float)$codAmount,

                'quantity' => (int) $order->items->sum('quantity'),
                'weight'   => (float)$request->input('weight'),

                'products_description' => collect($orderItems)->pluck('name')->implode(', '),

                // Store Shiprocket ids in courier_reference
                'courier_reference' => 'shiprocket_order_id=' . $shiprocketOrderId . ', shipment_id=' . $shipmentId,

                'awb_no' => $awb,

                // Save dimension columns you already have
                'shipment_length' => (float)$request->input('length'),
                'shipment_width'  => (float)$request->input('breadth'),
                'shipment_height' => (float)$request->input('height'),

                // seller/pickup details from Shiprocket response
                'seller_name'    => data_get($shippedBy, 'shipper_company_name'),
                'seller_address' => trim((string)data_get($shippedBy, 'shipper_address_1') . ' ' . (string)data_get($shippedBy, 'shipper_address_2')),
                'seller_invoice' => data_get($awbRes, 'response.data.invoice_no'),

                'pickup_name'    => data_get($shippedBy, 'shipper_company_name'),
                'pickup_address' => trim((string)data_get($shippedBy, 'shipper_address_1') . ' ' . (string)data_get($shippedBy, 'shipper_address_2')),
                'pickup_pin'     => (string)data_get($shippedBy, 'shipper_postcode'),
                'pickup_city'    => (string)data_get($shippedBy, 'shipper_city'),
                'pickup_state'   => (string)data_get($shippedBy, 'shipper_state'),
                'pickup_phone'   => (string)data_get($shippedBy, 'shipper_phone'),

                // return (RTO) details
                'return_address' => trim((string)data_get($shippedBy, 'rto_address_1') . ' ' . (string)data_get($shippedBy, 'rto_address_2')),
                'return_pin'     => (string)data_get($shippedBy, 'rto_postcode'),
                'return_city'    => (string)data_get($shippedBy, 'rto_city'),
                'return_state'   => (string)data_get($shippedBy, 'rto_state'),
                'return_phone'   => (string)data_get($shippedBy, 'rto_phone'),
                'return_country' => (string)data_get($shippedBy, 'rto_country'),

                'request_payload'  => $payload,
                'response_payload' => [
                    'created_response' => $created,
                    'awb_response'     => $awbRes,
                    'label_response'   => $labelRes,
                ],

                'error_message' => null,
                'booked_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Order punched to Shiprocket and shipment saved.',
                'data' => [
                    'order_id' => $order->id,
                    'shipments_table_id' => $shipmentRow->id,
                    'shiprocket_order_id' => $shiprocketOrderId,
                    'shiprocket_shipment_id' => (int)$shipmentId,
                    'awb' => $awb,
                    'label_url' => $labelUrl,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Shiprocket punchOrderById failed', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ]);

            // Save failed attempt (optional)
            try {
                OrderShipment::create([
                    'order_id' => $order->id,
                    'user_id'  => $order->user_id,
                    'courier' => 'Shiprocket',
                    'status'  => 'FAILED',
                    'customer_name' => $order->user->name ?? '',
                    'customer_phone' => $order->user->mobile ?? '',
                    'customer_email' => $order->user->email ?? '',
                    'shipping_address' => is_string($order->shipping_address) ? $order->shipping_address : json_encode($order->shipping_address),
                    'request_payload' => $payload ?? [],
                    'response_payload' => [],
                    'error_message' => $e->getMessage(),
                    'booked_at' => now(),
                ]);
            } catch (\Throwable $ignore) {}

            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Shiprocket punch failed.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function punchByPayload(Request $request, ShiprocketService $shiprocket)
    {
        $v = Validator::make($request->all(), [
            'order_id' => ['required', 'integer', 'exists:t_orders,id'],
            'payload' => ['required', 'array'],

            'payload.customer_name' => ['required', 'string', 'max:255'],
            'payload.customer_address' => ['required', 'string', 'max:500'],
            'payload.pin' => ['required', 'string', 'max:10'],
            'payload.city' => ['required', 'string', 'max:100'],
            'payload.state' => ['required', 'string', 'max:100'],
            'payload.phone' => ['required', 'string', 'max:20'],

            'payload.order_no' => ['required', 'string', 'max:50'],
            'payload.payment_mode' => ['required', 'in:Prepaid,COD'],
            'payload.total_amount' => ['required', 'numeric', 'min:0'],
            'payload.cod_amount' => ['required', 'numeric', 'min:0'],

            'payload.products_description' => ['required', 'string', 'max:500'],
            'payload.quantity' => ['required', 'integer', 'min:1'],
            'payload.weight' => ['required', 'numeric', 'min:0.1'],

            'payload.order_date' => ['required', 'date'],

            'payload.shipment_length' => ['required', 'numeric', 'min:1'],
            'payload.shipment_width'  => ['required', 'numeric', 'min:1'],
            'payload.shipment_height' => ['required', 'numeric', 'min:1'],

            // Optional extras you send
            'payload.seller_name' => ['nullable', 'string', 'max:255'],
            'payload.seller_address' => ['nullable', 'string', 'max:500'],
            'payload.seller_invoice' => ['nullable', 'string', 'max:100'],

            
            'payload.pickup_location_id' => ['nullable','integer'], 
            'payload.pickup_name' => ['nullable', 'string', 'max:255'],
            'payload.pickup_address' => ['nullable', 'string', 'max:500'],
            'payload.pickup_pin' => ['nullable'],
            'payload.pickup_city' => ['nullable', 'string', 'max:100'],
            'payload.pickup_state' => ['nullable', 'string', 'max:100'],
            'payload.pickup_phone' => ['nullable', 'string', 'max:20'],

            'payload.return_pin' => ['nullable'],
            'payload.return_city' => ['nullable', 'string', 'max:100'],
            'payload.return_state' => ['nullable', 'string', 'max:100'],
            'payload.return_phone' => ['nullable', 'string', 'max:20'],
            'payload.return_address' => ['nullable', 'string', 'max:500'],
            'payload.return_country' => ['nullable', 'string', 'max:100'],

            // Optional: choose courier
            'courier_id' => ['nullable', 'integer'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'data' => $v->errors(),
            ], 422);
        }

        $orderId = (int) $request->input('order_id');
        $p = $request->input('payload');

        // fetch order just to get user_id
        $order = OrderModel::find($orderId);

        // prevent duplicate shiprocket shipment
        $existing = OrderShipment::where('order_id', $orderId)
            ->where(function ($q) {
                $q->where('courier', 'like', '%Shiprocket%')
                ->orWhere('courier_reference', 'like', '%shiprocket_order_id=%');
            })
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'code' => 409,
                'success' => false,
                'message' => 'Shiprocket shipment already created for this order.',
                'data' => [
                    'shipments_table_id' => $existing->id,
                    'awb_no' => $existing->awb_no,
                    'courier_reference' => $existing->courier_reference,
                ],
            ], 409);
        }

        // Build Shiprocket order_items from your products_description + quantity
        // (Shiprocket requires sku + name + units + selling_price)
        $itemName = trim((string) ($p['products_description'] ?? 'Item'));
        $units = (int) ($p['quantity'] ?? 1);
        $totalAmount = (float) ($p['total_amount'] ?? 0);
        $unitPrice = $units > 0 ? round($totalAmount / $units, 2) : $totalAmount;

        $orderItems = [
            [
                'name' => $itemName,
                'sku' => 'SKU-' . $orderId,
                'units' => $units,
                'selling_price' => $unitPrice,
            ]
        ];

        // Shiprocket payload
        $shiprocketPayload = [
            'order_id' => (string) $p['order_no'], // your channel order number
            'order_date' => (string) $p['order_date'],
            'pickup_location' => (string) config('services.shiprocket.pickup_location'),

            'billing_customer_name' => (string) $p['customer_name'],
            'billing_last_name' => '',

            'billing_address' => (string) $p['customer_address'],
            'billing_address_2' => '',
            'billing_city' => (string) $p['city'],
            'billing_state' => (string) $p['state'],
            'billing_country' => 'India',
            'billing_pincode' => (string) $p['pin'],
            'billing_email' => $order->user->email ?? 'test@example.com',
            'billing_phone' => preg_replace('/\D+/', '', (string) $p['phone']),

            'shipping_is_billing' => true,

            'payment_method' => (string) $p['payment_mode'],
            'sub_total' => round((float) $p['total_amount'], 2),
            'order_items' => $orderItems,

            'length'  => (float) $p['shipment_length'],
            'breadth' => (float) $p['shipment_width'],
            'height'  => (float) $p['shipment_height'],
            'weight'  => (float) $p['weight'],
        ];

        $courierId = $request->input('courier_id');

        DB::beginTransaction();
        try {
            // 1) Create on shiprocket
            $created = $shiprocket->createOrderAdhoc($shiprocketPayload);

            $shipmentId = data_get($created, 'shipment_id');
            $shiprocketOrderId = data_get($created, 'order_id');

            if (!$shipmentId) {
                DB::rollBack();
                return response()->json([
                    'code' => 500,
                    'success' => false,
                    'message' => 'Shiprocket created response but shipment_id missing.',
                    'data' => $created,
                ], 500);
            }

            // Map status for DB
            $srStatus = strtoupper((string) data_get($created, 'status', 'NEW'));
            $mappedStatus = match ($srStatus) {
                'NEW' => 'booked',
                'CANCELED', 'CANCELLED' => 'cancelled',
                'DELIVERED' => 'delivered',
                'IN TRANSIT', 'IN_TRANSIT' => 'in_transit',
                default => 'booked',
            };

            // 2) Assign AWB
            $awbRes = $shiprocket->assignAwb((int)$shipmentId, $courierId ? (int)$courierId : null);
            $awbAssignStatus = (int) data_get($awbRes, 'awb_assign_status', 0);

            $awb = data_get($awbRes, 'response.data.awb_code') ?? null;
            $courierName = data_get($awbRes, 'response.data.courier_name') ?? null;

            // if AWB fails, keep it pending (optional but recommended)
            if ($awbAssignStatus !== 1) {
                $mappedStatus = 'pending';
            }

            // 3) Label (will be null if AWB not assigned)
            $labelRes = $shiprocket->generateLabel([(int)$shipmentId]);
            $labelUrl = data_get($labelRes, 'label_url');

            // 4) Save shipment row
            $shipmentRow = OrderShipment::create([
                'order_id' => $orderId,
                'user_id'  => $order->user_id,

                'courier' => $courierName ?: 'Shiprocket',
                'status' => $mappedStatus,

                'customer_name' => (string) $p['customer_name'],
                'customer_phone' => preg_replace('/\D+/', '', (string) $p['phone']),
                'customer_email' => $order->user->email ?? null,

                'shipping_address' => (string) $p['customer_address'],
                'shipping_pin' => (string) $p['pin'],
                'shipping_city' => (string) $p['city'],
                'shipping_state' => (string) $p['state'],

                'payment_mode' => (string) $p['payment_mode'],
                'total_amount' => (float) $p['total_amount'],
                'cod_amount' => (float) $p['cod_amount'],

                'quantity' => (int) $p['quantity'],
                'weight' => (float) $p['weight'],
                'products_description' => (string) $p['products_description'],

                'shipment_length' => (float) $p['shipment_length'],
                'shipment_width'  => (float) $p['shipment_width'],
                'shipment_height' => (float) $p['shipment_height'],

                'seller_name' => $p['seller_name'] ?? null,
                'seller_address' => $p['seller_address'] ?? null,
                'seller_invoice' => $p['seller_invoice'] ?? null,

                'pickup_location_id' => isset($p['pickup_location_id']) ? (int)$p['pickup_location_id'] : null,
                'pickup_name' => $p['pickup_name'] ?? null,
                'pickup_address' => $p['pickup_address'] ?? null,
                'pickup_pin' => isset($p['pickup_pin']) ? (string)$p['pickup_pin'] : null,
                'pickup_city' => $p['pickup_city'] ?? null,
                'pickup_state' => $p['pickup_state'] ?? null,
                'pickup_phone' => isset($p['pickup_phone']) ? (string)$p['pickup_phone'] : null,

                'return_pin' => isset($p['return_pin']) ? (string)$p['return_pin'] : null,
                'return_city' => $p['return_city'] ?? null,
                'return_state' => $p['return_state'] ?? null,
                'return_phone' => isset($p['return_phone']) ? (string)$p['return_phone'] : null,
                'return_address' => $p['return_address'] ?? null,
                'return_country' => $p['return_country'] ?? 'India',

                'courier_reference' => 'shiprocket_order_id=' . $shiprocketOrderId . ', shipment_id=' . $shipmentId,
                'awb_no' => $awb,

                'request_payload' => [
                    'input_payload' => $p,
                    'shiprocket_payload' => $shiprocketPayload,
                ],
                'response_payload' => [
                    'created_response' => $created,
                    'awb_response' => $awbRes,
                    'label_response' => $labelRes,
                ],

                'error_message' => null,
                'booked_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Payload punched to Shiprocket and shipment saved.',
                'data' => [
                    'order_id' => $orderId,
                    'shipments_table_id' => $shipmentRow->id,
                    'shiprocket_order_id' => $shiprocketOrderId,
                    'shiprocket_shipment_id' => (int)$shipmentId,
                    'awb' => $awb,
                    'label_url' => $labelUrl,
                    'status' => $mappedStatus,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Shiprocket punch failed.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    // Cancel order by order_id or shiprocket_order_id
    public function cancelOrder(Request $request, ShiprocketService $shiprocket)
    {
        $v = Validator::make($request->all(), [
            // You can pass either one:
            'shiprocket_order_id' => ['nullable','integer'],
            'order_id'            => ['nullable','integer','exists:t_orders,id'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'data' => $v->errors(),
            ], 422);
        }

        if (!$request->filled('shiprocket_order_id') && !$request->filled('order_id')) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Pass either shiprocket_order_id OR order_id.',
                'data' => [],
            ], 422);
        }

        $srOrderId = $request->input('shiprocket_order_id');

        // If user sent local order_id, find shiprocket_order_id from t_order_shipments
        $shipmentRow = null;

        if (!$srOrderId) {
            $orderId = (int) $request->input('order_id');

            $shipmentRow = OrderShipment::where('order_id', $orderId)
                ->where(function ($q) {
                    $q->where('courier', 'like', '%Shiprocket%')
                    ->orWhere('courier_reference', 'like', '%shiprocket_order_id=%')
                    ->orWhereNotNull('response_payload');
                })
                ->latest('id')
                ->first();

            if (!$shipmentRow) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No Shiprocket shipment found for this order_id.',
                    'data' => [],
                ], 404);
            }

            // Prefer response_payload.created_response.order_id
            $srOrderId = data_get($shipmentRow->response_payload, 'created_response.order_id');

            // Fallback: parse from courier_reference: "shiprocket_order_id=123, shipment_id=..."
            if (!$srOrderId && $shipmentRow->courier_reference) {
                if (preg_match('/shiprocket_order_id\s*=\s*(\d+)/i', $shipmentRow->courier_reference, $m)) {
                    $srOrderId = (int) $m[1];
                }
            }
        }

        if (!$srOrderId) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Shiprocket order id not found. Pass shiprocket_order_id explicitly.',
                'data' => [],
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Shiprocket cancel expects {"ids":[srOrderId]} :contentReference[oaicite:2]{index=2}
            $cancelRes = $shiprocket->cancelOrders([(int)$srOrderId]);

            // Update local DB if we have shipment row
            if ($shipmentRow) {
                $shipmentRow->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'response_payload' => array_merge((array) $shipmentRow->response_payload, [
                        'cancel_response' => $cancelRes,
                    ]),
                ]);
            } else {
                // If user provided shiprocket_order_id only, update latest matching row (optional)
                $row = OrderShipment::where('courier_reference', 'like', '%shiprocket_order_id='.(int)$srOrderId.'%')
                    ->latest('id')
                    ->first();
                if ($row) {
                    $row->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'response_payload' => array_merge((array) $row->response_payload, [
                            'cancel_response' => $cancelRes,
                        ]),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Shiprocket order cancel request submitted.',
                'data' => [
                    'shiprocket_order_id' => (int)$srOrderId,
                    'cancel_response' => $cancelRes,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Cancel failed.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

}