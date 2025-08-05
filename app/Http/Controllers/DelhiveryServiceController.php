<?php

namespace App\Http\Controllers;
use App\Services\DelhiveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DelhiveryServiceController extends Controller
{
    protected DelhiveryService $delhiveryService;

    public function __construct(DelhiveryService $delhiveryService)
    {
        $this->delhiveryService = $delhiveryService;
    }

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

    public function createOrder(Request $request)
    {
        // 1. Validate the incoming request data for order creation
        // You MUST validate the structure of the order data
        // $validator = Validator::make($request->all(), [
        //     // Add validation rules for your order payload
        //     'customer_name' => 'required',
        //     'customer_address' => 'required',
        //     'pin' => 'required',
        //     'city' => 'required',
        //     'state' => 'required',
        //     'phone' => 'required',
        //     'order' => 'required',
        //     // ... and so on
        // ]);

        // $validator = Validator::make($request->all(), [
        //     'customer_name' => 'required',
        //     'customer_address' => 'required',
        //     'pin' => 'required',
        //     'city' => 'required',
        //     'state' => 'required',
        //     'phone' => 'required',
        //     'order' => 'required',
        //     // Add other required fields here...
        // ]);

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required',
            'customer_address' => 'required',
            'pin' => 'required',
            'city' => 'required',
            'state' => 'required',
            'phone' => 'required',
            'order' => 'required',
            'shipment_width' => 'required',
            'shipment_height' => 'required',
            'shipping_mode' => 'required',
            'return_pin' => 'nullable',
            'return_city' => 'nullable',
            'return_phone' => 'nullable',
            'return_address' => 'nullable',
            'return_state' => 'nullable',
            'return_country' => 'nullable',
            'products_description' => 'nullable',
            'hsn_code' => 'nullable',
            'cod_amount' => 'nullable',
            'order_date' => 'nullable',
            'total_amount' => 'nullable',
            'seller_address' => 'nullable',
            'seller_name' => 'nullable',
            'seller_invoice' => 'nullable',
            'quantity' => 'nullable',
            'waybill' => 'nullable',
            'weight' => 'nullable',
            'address_type' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $orderData = $request->all();
        $response = $this->delhiveryService->placeOrder($orderData);

        // if (isset($response['error'])) {
        //     Log::error('Delhivery API Error: ' . json_encode($response));
        //     return response()->json(['error' => $response['error']], 400);
        // }
        if (isset($response['error'])) {
            Log::error('Delhivery API Error: ' . json_encode($response));
            return response()->json($response, 400);
        }

        return response()->json(['success' => true, 'data' => $response]);
    }

    public function trackMultipleShipments(Request $request)
    {
        // This method should receive the Request object
        $validator = Validator::make($request->all(), [
            'waybills' => 'required|array',
            'waybills.*' => 'string', // Ensure each element is a string
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $waybillNumbers = $request->input('waybills');
        $response = $this->delhiveryService->trackMultipleShipments($waybillNumbers);

        if (isset($response['Error'])) {
            return response()->json(['error' => $response['Error']], 400);
        }

        return response()->json($response);
    }
}