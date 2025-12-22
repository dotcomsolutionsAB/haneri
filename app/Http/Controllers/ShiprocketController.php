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

        // 2) Parse shipping_address from t_orders.shipping_address
        // We will support: JSON string OR array OR plain text
        $shipping = $order->shipping_address;

        if (is_string($shipping)) {
            $decoded = json_decode($shipping, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $shipping = $decoded;
            }
        }

        // Defaults (you MUST map these correctly as per your shipping_address structure)
        $custName  = $order->user->name ?? 'Customer';
        $custEmail = $order->user->email ?? 'test@example.com';
        $custPhone = $order->user->mobile ?? ($order->user->phone ?? '');

        // If shipping is array, try to pull fields
        $address1 = is_array($shipping) ? ($shipping['address'] ?? $shipping['address1'] ?? $shipping['line1'] ?? '') : (string)$shipping;
        $address2 = is_array($shipping) ? ($shipping['address2'] ?? $shipping['line2'] ?? '') : '';
        $city     = is_array($shipping) ? ($shipping['city'] ?? '') : '';
        $state    = is_array($shipping) ? ($shipping['state'] ?? '') : '';
        $pincode  = is_array($shipping) ? ($shipping['pincode'] ?? $shipping['pin'] ?? '') : '';
        $country  = is_array($shipping) ? ($shipping['country'] ?? 'India') : 'India';
        $phone    = is_array($shipping) ? ($shipping['phone'] ?? $custPhone) : $custPhone;

        // If important fields missing, return clear error
        if (trim($address1) === '' || trim($city) === '' || trim($state) === '' || trim($pincode) === '' || trim($phone) === '') {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Shipping address is incomplete in t_orders.shipping_address. Required: address, city, state, pincode, phone.',
                'data' => [
                    'shipping_address_raw' => $order->shipping_address,
                    'parsed' => [
                        'address1' => $address1,
                        'city' => $city,
                        'state' => $state,
                        'pincode' => $pincode,
                        'phone' => $phone,
                    ],
                ],
            ], 422);
        }

        // 3) Build Shiprocket items
        $orderItems = [];
        $subTotal = 0;

        foreach ($order->items as $it) {
            $name = $it->product->name ?? ('Product ' . $it->product_id);
            $sku  = $it->product->sku ?? ('SKU-' . $it->product_id);

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
        $channelOrderId = 'ORDER-' . $order->id; // If you have so_no, use that instead

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

            $shipmentRow = OrderShipment::create([
                'order_id' => $order->id,
                'user_id'  => $order->user_id,

                'courier' => $courierName ?: 'Shiprocket',
                'status'  => data_get($created, 'status', 'NEW'),

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

}