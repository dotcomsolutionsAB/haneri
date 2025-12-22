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
}