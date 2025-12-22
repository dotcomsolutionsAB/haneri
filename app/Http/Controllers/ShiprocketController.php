<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\services\ShiprocketService;
use Illuminate\Support\Facades\Validator;

class ShiprocketController extends Controller
{
    public function createShipment(Request $request, ShiprocketService $shiprocket)
    {
        // ✅ This API is "JUST OKAY": it accepts payload and pushes to Shiprocket
        // You can later connect it to your OrderModel automatically.

        $validator = Validator::make($request->all(), [
            'order_id' => ['required', 'string', 'max:50'],
            'order_date' => ['required', 'date'],
            'billing_customer_name' => ['required', 'string', 'max:255'],
            'billing_address' => ['required', 'string', 'max:500'],
            'billing_city' => ['required', 'string', 'max:100'],
            'billing_state' => ['required', 'string', 'max:100'],
            'billing_country' => ['required', 'string', 'max:100'],
            'billing_pincode' => ['required', 'string', 'max:10'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['required', 'string', 'max:20'],

            'payment_method' => ['required', 'in:COD,Prepaid'],
            'sub_total' => ['required', 'numeric', 'min:0'],

            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.name' => ['required', 'string'],
            'order_items.*.sku' => ['required', 'string'],
            'order_items.*.units' => ['required', 'integer', 'min:1'],
            'order_items.*.selling_price' => ['required', 'numeric', 'min:0'],

            // optional:
            'length' => ['nullable','numeric','min:0'],
            'breadth' => ['nullable','numeric','min:0'],
            'height' => ['nullable','numeric','min:0'],
            'weight' => ['nullable','numeric','min:0'],
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

        // Recommended fields:
        $payload['shipping_is_billing'] = $payload['shipping_is_billing'] ?? true;

        // 1) Create order
        $created = $shiprocket->createOrderAdhoc($payload);

        $shipmentId = data_get($created, 'shipment_id');
        $orderIdSr  = data_get($created, 'order_id');

        // If shipment_id not found, return raw response for debugging
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
}