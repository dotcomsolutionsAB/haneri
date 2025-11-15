<?php

namespace App\Http\Controllers;

use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\OrderShipment;
use App\services\DelhiveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

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

    public function createShipByOrder(Request $request)
    {
        // 1) Validate only order_id – everything else is auto-fetched
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:t_orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $orderId = (int) $request->input('order_id');

        try {
            // 2) Load order + user
            $order = OrderModel::with('user')->findOrFail($orderId);
            $user  = $order->user;

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for this order.',
                    'data'    => [],
                ], 404);
            }

            // 3) Load order items with product + variant (for name & weight)
            $items = OrderItemModel::with([
                    'product:id,name',
                    'variant:id,weight'
                ])
                ->where('order_id', $order->id)
                ->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No items found for this order.',
                    'data'    => [],
                ], 400);
            }

            // 4) Build products_description, total quantity, total weight
            $descParts   = [];
            $totalQty    = 0;
            $totalWeight = 0.0; // in kg

            foreach ($items as $item) {
                $name = optional($item->product)->name ?? ('Product #'.$item->product_id);
                $qty  = (int) $item->quantity;

                $descParts[] = $name.' x '.$qty;
                $totalQty   += $qty;

                // weight from variant (if available)
                $variantWeight = optional($item->variant)->weight; // assume in kg
                if (!is_null($variantWeight)) {
                    $totalWeight += ((float) $variantWeight) * $qty;
                }
            }

            if ($totalWeight <= 0) {
                // Fallback – you can change default
                $totalWeight = 1.0;
            }

            $productsDescription = implode(', ', $descParts);

            // 5) Map shipping details
            // NOTE: adjust these column names to your schema
            $shippingAddress = $order->shipping_address;

            // Try from order first, else from user (if you store them there)
            $pin   = $order->shipping_pin   ?? $user->pin   ?? null;
            $city  = $order->shipping_city  ?? $user->city  ?? null;
            $state = $order->shipping_state ?? $user->state ?? null;

            if (!$pin || !$city || !$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing shipping pincode/city/state for this order. Please complete address first.',
                    'data'    => [
                        'pin'   => $pin,
                        'city'  => $city,
                        'state' => $state,
                    ],
                ], 422);
            }

            // 6) Payment mode from order.payment_status
            // tweak mapping as per your logic
            $paymentMode = $order->payment_status === 'paid' ? 'Prepaid' : 'COD';
            $codAmount   = $paymentMode === 'COD' ? (float) $order->total_amount : 0.0;

            // 7) Seller / pickup details – take from config or hard-code
            // Create config/shipping.php or edit below defaults
            $sellerName    = config('shipping.seller_name', 'Your Store Name');
            $sellerAddress = config('shipping.seller_address', 'Your Warehouse Address');
            $sellerInvoice = 'INV-' . $order->id; // or $order->invoice_no etc.

            $pickupName    = config('shipping.pickup.name', 'Default Pickup');
            $pickupAddress = config('shipping.pickup.address', 'Your Warehouse Address');
            $pickupPin     = config('shipping.pickup.pin', '700001');
            $pickupCity    = config('shipping.pickup.city', 'Kolkata');
            $pickupState   = config('shipping.pickup.state', 'West Bengal');
            $pickupPhone   = config('shipping.pickup.phone', '9000000000');

            // 8) Build payload for DelhiveryService->placeOrder()
            $orderData = [
                // Customer
                'customer_name'        => $user->name,
                'customer_address'     => $shippingAddress,
                'pin'                  => $pin,
                'city'                 => $city,
                'state'                => $state,
                'phone'                => $user->mobile,

                // Order / payment
                'order_no'             => (string) $order->id,   // or your custom order number field
                'payment_mode'         => $paymentMode,
                'total_amount'         => (float) $order->total_amount,
                'cod_amount'           => $codAmount,

                // Products
                'products_description' => $productsDescription,
                'quantity'             => $totalQty,
                'weight'               => round($totalWeight, 3), // in kg
                'order_date'           => $order->created_at
                                            ? $order->created_at->toDateString()
                                            : Carbon::now()->toDateString(),

                // Seller
                'seller_name'          => $sellerName,
                'seller_address'       => $sellerAddress,
                'seller_invoice'       => $sellerInvoice,

                // Pickup
                'pickup_name'          => $pickupName,
                'pickup_address'       => $pickupAddress,
                'pickup_pin'           => $pickupPin,
                'pickup_city'          => $pickupCity,
                'pickup_state'         => $pickupState,
                'pickup_phone'         => $pickupPhone,

                // Optional
                'shipment_width'       => null,
                'shipment_height'      => null,
                'shipping_mode'        => 'Surface',
                'address_type'         => 'home',
                'return_pin'           => $pickupPin,
                'return_city'          => $pickupCity,
                'return_phone'         => $pickupPhone,
                'return_address'       => $pickupAddress,
                'return_state'         => $pickupState,
                'return_country'       => 'India',
            ];

            // 9) Call DelhiveryService
            $delhiveryService = new DelhiveryService();
            $apiResponse      = $delhiveryService->placeOrder($orderData);

            // 10) Prepare / update shipment record in DB
            $shipment = OrderShipment::firstOrNew(['order_id' => $order->id]);
            $shipment->user_id            = $order->user_id;
            $shipment->courier            = 'delhivery';
            $shipment->customer_name      = $orderData['customer_name'];
            $shipment->customer_phone     = $orderData['phone'];
            $shipment->customer_email     = $user->email;
            $shipment->shipping_address   = $orderData['customer_address'];
            $shipment->shipping_pin       = $orderData['pin'];
            $shipment->shipping_city      = $orderData['city'];
            $shipment->shipping_state     = $orderData['state'];
            $shipment->payment_mode       = $orderData['payment_mode'];
            $shipment->total_amount       = $orderData['total_amount'];
            $shipment->cod_amount         = $orderData['cod_amount'];
            $shipment->quantity           = $orderData['quantity'];
            $shipment->weight             = $orderData['weight'];
            $shipment->products_description = $orderData['products_description'];
            $shipment->pickup_name        = $orderData['pickup_name'];
            $shipment->pickup_address     = $orderData['pickup_address'];
            $shipment->pickup_pin         = $orderData['pickup_pin'];
            $shipment->pickup_city        = $orderData['pickup_city'];
            $shipment->pickup_state       = $orderData['pickup_state'];
            $shipment->pickup_phone       = $orderData['pickup_phone'];
            $shipment->request_payload    = $orderData;
            $shipment->response_payload   = $apiResponse;

            // If Delhivery returned an error
            if (isset($apiResponse['error'])) {
                $shipment->status        = 'failed';
                $shipment->error_message = $apiResponse['error'];
                $shipment->save();

                return response()->json([
                    'success' => false,
                    'message' => $apiResponse['error'],
                    'data'    => $apiResponse['raw'] ?? [],
                ], 400);
            }

            // Extract AWB / waybill – adjust keys based on your actual response
            $awbNo   = $apiResponse['packages'][0]['waybill'] ?? null;
            $refNum  = $apiResponse['packages'][0]['refnum']  ?? null;

            $shipment->awb_no           = $awbNo;
            $shipment->courier_reference= $refNum;
            $shipment->status           = 'booked';
            $shipment->booked_at        = Carbon::now();
            $shipment->error_message    = null;
            $shipment->save();

            return response()->json([
                'success' => true,
                'message' => 'Shipment created on Delhivery for this order.',
                'data'    => [
                    'order_id'    => $order->id,
                    'shipment_id' => $shipment->id,
                    'awb_no'      => $shipment->awb_no,
                    'courier'     => $shipment->courier,
                    'status'      => $shipment->status,
                    'api'         => $apiResponse,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('createShipByOrder (auto) failed for order '.$orderId.': '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unexpected error while creating shipment: '.$e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }


    // auto ship once run it fetch order id
    public function autoShipSetup(Request $request, $orderId)
    {
        $order = OrderModel::with('user')->findOrFail($orderId);

        // If it already exists, just return it
        $existing = OrderShipment::where('order_id', $order->id)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Shipment setup already exists for this order.',
                'data'    => $existing,
            ]);
        }

        $user = $order->user;

        $shipment = OrderShipment::create([
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'courier'         => 'delhivery',
            'status'          => 'setup',

            'customer_name'   => $user->name ?? 'Customer',
            'customer_phone'  => $user->mobile ?? null,
            'customer_email'  => $user->email ?? null,
            'shipping_address'=> $order->shipping_address,
            'shipping_pin'    => $order->shipping_pin ?? null,
            'shipping_city'   => $order->shipping_city ?? null,
            'shipping_state'  => $order->shipping_state ?? null,

            'payment_mode'    => 'Prepaid',
            'total_amount'    => $order->total_amount,
            'cod_amount'      => 0,
            'quantity'        => 1,
            'products_description' => 'Order #'.$order->id.' items',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment setup created for order.',
            'data'    => $shipment,
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

    public function getTat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'origin_pin'         => 'required|digits:6',
            'destination_pin'    => 'required|digits:6',
            'mot'                => 'required|in:S,E',           // Surface / Express
            'pdt'                => 'nullable|in:B2B,B2C',
            'expected_pickup_date' => 'nullable|string',         // you can tighten this to date_format if needed
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $originPin         = $request->input('origin_pin');
        $destinationPin    = $request->input('destination_pin');
        $mot               = $request->input('mot', 'S');
        $pdt               = $request->input('pdt');
        $expectedPickup    = $request->input('expected_pickup_date');

        $delhiveryService  = new DelhiveryService();
        $response          = $delhiveryService->getTat($originPin, $destinationPin, $mot, $pdt, $expectedPickup);

        if (isset($response['error'])) {
            return response()->json([
                'success' => false,
                'message' => $response['error'],
                'data'    => $response['raw'] ?? [],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'TAT fetched successfully.',
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