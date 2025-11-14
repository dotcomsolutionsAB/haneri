<?php

namespace App\Http\Controllers;
use App\services\DelhiveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class DelhiveryServiceController extends Controller
{
    // protected DelhiveryService $delhiveryService;

    // public function __construct(DelhiveryService $delhiveryService)
    // {
    //     $this->delhiveryService = $delhiveryService;
    // }

    public function test()
    {
        $url = rtrim(env('DELIVERY_ONE_URL'), '/') . '/ping';

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('DELIVERY_ONE_TOKEN'),
            'Accept'        => 'application/json',
        ])->get($url);

        return response()->json([
            'url'         => $url,
            'status'      => $response->status(),
            'successful'  => $response->successful(),
            'body'        => $response->body(),   // raw response (may be HTML or empty)
            'json'        => $response->json(),   // will be null if not valid JSON
        ]);
    }

    public function checkPincodeServiceability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pincode' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $delhiveryService = new DelhiveryService();

        $pincode  = $request->input('pincode');
        $response = $delhiveryService->checkPincodeServiceability($pincode);

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['error'],
                'data'    => [],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pincode serviceability fetched.',
            'data'    => $response,
        ]);
    }

    public function getShippingCost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_pin'      => 'required|digits:6',
            'destination_pin' => 'required|digits:6',
            'cod_amount'      => 'required|numeric',
            'weight'          => 'required|numeric',   // in kg
            'payment_type'    => 'nullable|in:Pre-paid,COD',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $originPin      = $request->input('origin_pin');
        $destinationPin = $request->input('destination_pin');
        $codAmount      = $request->input('cod_amount');
        $weight         = $request->input('weight');
        $paymentType    = $request->input('payment_type', 'Pre-paid');

        // same pattern as pincode: manual service
        $delhiveryService = new DelhiveryService();
        $response = $delhiveryService->getShippingCost($originPin, $destinationPin, $codAmount, $weight, $paymentType);

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['error'],
                'data'    => [],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipping cost fetched.',
            'data'    => $response,
        ]);
    }

    public function createOrder(Request $request)
    {
        // Basic validation – you can tighten later
        $validator = Validator::make($request->all(), [
            // Customer details
            'customer_name'       => 'required|string',
            'customer_address'    => 'required|string',
            'pin'                 => 'required|digits:6',
            'city'                => 'required|string',
            'state'               => 'required|string',
            'phone'               => 'required|string',

            // Order / payment
            'order_no'            => 'required|string',          // Your internal order id
            'payment_mode'        => 'required|in:Prepaid,COD',
            'total_amount'        => 'required|numeric',
            'cod_amount'          => 'nullable|numeric',

            // Product summary
            'products_description'=> 'required|string',
            'quantity'            => 'required|integer',
            'weight'              => 'required|numeric',          // in kg
            'order_date'          => 'required|date',             // YYYY-MM-DD

            // Seller info (your shop)
            'seller_name'         => 'required|string',
            'seller_address'      => 'required|string',
            'seller_invoice'      => 'required|string',

            // Pickup location
            'pickup_name'         => 'required|string',
            'pickup_address'      => 'required|string',
            'pickup_pin'          => 'required|digits:6',
            'pickup_city'         => 'required|string',
            'pickup_state'        => 'required|string',
            'pickup_phone'        => 'required|string',

            // Optional fields
            'shipment_width'      => 'nullable|numeric',
            'shipment_height'     => 'nullable|numeric',
            'shipping_mode'       => 'nullable|string',
            'address_type'        => 'nullable|string',
            'return_pin'          => 'nullable|digits:6',
            'return_city'         => 'nullable|string',
            'return_phone'        => 'nullable|string',
            'return_address'      => 'nullable|string',
            'return_state'        => 'nullable|string',
            'return_country'      => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $payload = $request->all();

        // manual service instance (no DI to avoid earlier issues)
        $delhiveryService = new DelhiveryService();
        $response = $delhiveryService->placeOrder($payload);

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['error'],
                'data'    => $response['raw'] ?? [],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Delhivery order created successfully.',
            'data'    => $response,
        ]);
    }

    public function trackShipments(Request $request)
    {
        $waybill = $request->query('waybill');
        $refIds  = $request->query('ref_ids'); // optional

        if (!$waybill) {
            return response()->json([
                'success' => false,
                'message' => 'The waybill parameter is required.',
                'data'    => [],
            ], 422);
        }

        // Multiple comma-separated waybills supported by Delhivery
        $waybillList = array_map('trim', explode(',', $waybill));

        // Manual service (no DI)
        $delhiveryService = new DelhiveryService();

        $response = $delhiveryService->trackShipments($waybillList);

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['error'],
                'data'    => [],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment tracking fetched.',
            'data'    => $response,
        ]);
    }


    /**
     * Endpoint to track one or more shipments.
     * This replaces the old trackMultipleShipments.
     */
    // public function trackShipments(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'waybills' => 'required|array',
    //         'waybills.*' => 'string',
    //     ]);
        
    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }

    //     $waybillNumbers = $request->input('waybills');
    //     $response = $this->delhiveryService->trackShipments($waybillNumbers);

    //     if (isset($response['error'])) {
    //         return response()->json($response, 400);
    //     }

    //     return response()->json($response);
    // }

    // public function createOrder(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'customer_name' => 'required',
    //         'customer_address' => 'required',
    //         'pin' => 'required',
    //         'city' => 'required',
    //         'state' => 'required',
    //         'phone' => 'required',
    //         'order' => 'required',
    //         'shipment_width' => 'required',
    //         'shipment_height' => 'required',
    //         'shipping_mode' => 'required',
    //         'return_pin' => 'nullable',
    //         'return_city' => 'nullable',
    //         'return_phone' => 'nullable',
    //         'return_address' => 'nullable',
    //         'return_state' => 'nullable',
    //         'return_country' => 'nullable',
    //         'products_description' => 'nullable',
    //         'hsn_code' => 'nullable',
    //         'cod_amount' => 'nullable',
    //         'order_date' => 'nullable',
    //         'total_amount' => 'nullable',
    //         'seller_address' => 'nullable',
    //         'seller_name' => 'nullable',
    //         'seller_invoice' => 'nullable',
    //         'quantity' => 'nullable',
    //         'waybill' => 'nullable',
    //         'weight' => 'nullable',
    //         'address_type' => 'nullable',
    //         //'end_date' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }

    //     $orderData = $request->all();
    //     $response = $this->delhiveryService->placeOrder($orderData);

    //     // if (isset($response['error'])) {
    //     //     Log::error('Delhivery API Error: ' . json_encode($response));
    //     //     return response()->json(['error' => $response['error']], 400);
    //     // }
    //     if (isset($response['error'])) {
    //         Log::error('Delhivery API Error: ' . json_encode($response));
    //         return response()->json($response, 400);
    //     }

    //     return response()->json(['success' => true, 'data' => $response]);
    // }
    /**
     * Endpoint to get the shipping cost.
     */
    // public function getShippingCost(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'origin_pin' => 'required|string|size:6',
    //         'destination_pin' => 'required|string|size:6',
    //         'cod_amount' => 'required|numeric',
    //         'weight' => 'required|numeric', // in kg
    //         'payment_type' => 'nullable|in:Pre-paid,COD',
    //     ]);
    
    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }
        
    //     $originPin = $request->input('origin_pin');
    //     $destinationPin = $request->input('destination_pin');
    //     $codAmount = $request->input('cod_amount');
    //     $weight = $request->input('weight');
    //     $paymentType = $request->input('payment_type', 'Pre-paid');
    
    //     $response = $this->delhiveryService->getShippingCost($originPin, $destinationPin, $codAmount, $weight, $paymentType);
        
    //     if (isset($response['error'])) {
    //         return response()->json($response, 400);
    //     }
    
    //     return response()->json($response);
    // }
    /**
     * Endpoint to check if a pincode is serviceable.
     */
    // public function checkPincodeServiceability(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'pincode' => 'required|string|size:6',
    //     ]);
    
    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }
    
    //     $pincode = $request->input('pincode');
    //     $response = $this->delhiveryService->checkPincodeServiceability($pincode);
    
    //     if (isset($response['error'])) {
    //         return response()->json($response, 400);
    //     }
    
    //     return response()->json($response);
    // }
    
    // public function trackMultipleShipments(Request $request)
    // {
    //     // This method should receive the Request object
    //     $validator = Validator::make($request->all(), [
    //         'waybills' => 'required|array',
    //         'waybills.*' => 'string', // Ensure each element is a string
    //     ]);
        
    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }

    //     $waybillNumbers = $request->input('waybills');
    //     $response = $this->delhiveryService->trackMultipleShipments($waybillNumbers);

    //     if (isset($response['Error'])) {
    //         return response()->json(['error' => $response['Error']], 400);
    //     }

    //     return response()->json($response);
    // }
    // public function createOrder(Request $request)
    // {
    //     // 1. Validate the incoming request data for order creation
    //     // You MUST validate the structure of the order data
    //     $validator = Validator::make($request->all(), [
    //         // Add validation rules for your order payload
    //         'customer_name' => 'required',
    //         'customer_address' => 'required',
    //         // ... and so on
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }
        
    //     $orderData = $request->all();
    //     $response = $this->delhiveryService->placeOrder($orderData);
    //     // $response = $this->delhiveryService->debugPlaceOrder($orderData);

    //     if (isset($response['Error'])) {
    //         return response()->json(['error' => $response['Error']], 400);
    //     }

    //     return response()->json($response);
    // }
}