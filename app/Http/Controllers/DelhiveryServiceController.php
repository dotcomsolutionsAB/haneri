<?php

namespace App\Http\Controllers;

use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\OrderShipment;
use App\Models\PickupLocationModel;
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

    // Checking 
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
    // public function getShippingCost(Request $request)
    // {
    //     // 1. Basic validation for "through"
    //     $validator = Validator::make($request->all(), [
    //         'through'   => 'required|in:order,simple',

    //         // when through = order
    //         'order_id'  => 'required_if:through,order|integer|exists:t_orders,id',

    //         // when through = simple
    //         'origin_pin'      => 'required_if:through,simple|digits:6',
    //         'destination_pin' => 'required_if:through,simple|digits:6',
    //         'cod_amount'      => 'required_if:through,simple|numeric',
    //         'weight'          => 'required_if:through,simple|numeric',
    //         'payment_type'    => 'nullable|in:Pre-paid,COD',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'code'    => 422,
    //             'success' => false,
    //             'message' => 'Validation error',
    //             'data'    => $validator->errors(),
    //         ], 422);
    //     }

    //     $through = $request->input('through');

    //     // =========================
    //     // CASE 1: through = "order"
    //     // =========================
    //     if ($through === 'order') {
    //         $orderId = $request->input('order_id');

    //         // Get latest shipment for this order
    //         $shipment = OrderShipment::where('order_id', $orderId)
    //             ->orderByDesc('id')
    //             ->first();

    //         if (!$shipment) {
    //             return response()->json([
    //                 'code'    => 404,
    //                 'success' => false,
    //                 'message' => 'No shipment record found for this order.',
    //                 'data'    => [],
    //             ], 404);
    //         }

    //         // You can also fetch order if needed
    //         $order = OrderModel::find($orderId);

    //         // ---- Map DB fields to Delhivery params ----
    //         // Adjust these according to your real DB data
    //         $originPin      = $shipment->pickup_pin;     // pickup pincode
    //         $destinationPin = $shipment->shipping_pin;   // customer pincode
    //         $weight         = $shipment->weight ?? 1;    // default to 1kg if null

    //         // Payment / COD logic
    //         // Assuming payment_mode in ['Pre-paid','COD'] or similar
    //         $paymentMode = $shipment->payment_mode ?? 'Pre-paid';

    //         $paymentType = $paymentMode === 'COD' ? 'COD' : 'Pre-paid';

    //         // If you store cod_amount separately, use that, else fall back to total_amount
    //         $codAmount = $paymentType === 'COD'
    //             ? ($shipment->cod_amount ?? $shipment->total_amount ?? 0)
    //             : 0;

    //     } else {
    //     // =========================
    //     // CASE 2: through = "simple"
    //     // =========================
    //         $originPin      = $request->input('origin_pin');
    //         $destinationPin = $request->input('destination_pin');
    //         $codAmount      = $request->input('cod_amount');
    //         $weight         = $request->input('weight');
    //         $paymentType    = $request->input('payment_type', 'Pre-paid');
    //     }

    //     // =========================
    //     // Call Delhivery Service
    //     // =========================
    //     $delhiveryService = new DelhiveryService();

    //     $response = $delhiveryService->getShippingCost(
    //         $originPin,
    //         $destinationPin,
    //         $codAmount,
    //         $weight,
    //         $paymentType
    //     );

    //     if (isset($response['error'])) {
    //         return response()->json([
    //             'code'    => 400,
    //             'success' => false,
    //             'message' => $response['error'],
    //             'data'    => [],
    //         ], 400);
    //     }

    //     return response()->json([
    //         'code'    => 200,
    //         'success' => true,
    //         'message' => 'Shipping cost fetched.',
    //         'data'    => $response,
    //     ]);
    // }

    public function getShippingCost(Request $request)
    {
        // ✅ Your rules: prepaid always, cod always 0, ss always Delivered
        $validator = Validator::make($request->all(), [
            'through' => 'required|in:order,simple',

            // through = order
            'order_id' => 'required_if:through,order|integer|exists:t_orders,id',

            // through = simple
            'origin_pin'      => 'required_if:through,simple|digits:6',
            'destination_pin' => 'required_if:through,simple|digits:6',
            'weight'          => 'required_if:through,simple|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $through = $request->input('through');

        // =========================
        // Resolve pins + weight
        // =========================
        if ($through === 'order') {
            $orderId = (int) $request->input('order_id');

            $shipment = OrderShipment::where('order_id', $orderId)->orderByDesc('id')->first();
            if (!$shipment) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'No shipment record found for this order.',
                    'data'    => [],
                ], 404);
            }

            $originPin      = $shipment->pickup_pin;
            $destinationPin = $shipment->shipping_pin;
            $weightInput    = $shipment->weight ?? 1; // assume kg in DB; adjust if you store grams

            if (!$originPin || !$destinationPin) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'Pickup pin / Shipping pin missing in shipment record.',
                    'data'    => [],
                ], 422);
            }
        } else {
            $originPin      = $request->input('origin_pin');
            $destinationPin = $request->input('destination_pin');
            $weightInput    = $request->input('weight'); // in KG as per your payload
        }

        // Delhivery expects cgm in GRAMS (doc: "Only in Grams Unit") :contentReference[oaicite:2]{index=2}
        $cgm = $this->toDelhiveryGrams($weightInput);

        $baseQuery = [
            'ss'    => 'Delivered',     // ✅ delivery only (no RTO)
            'd_pin' => $destinationPin,
            'o_pin' => $originPin,
            'cgm'   => $cgm,
            'pt'    => 'Pre-paid',      // ✅ always prepaid
            'cod'   => 0,               // ✅ always 0
        ];

        $delhiveryService = new DelhiveryService();

        // =========================
        // 4 blocks as you want
        // =========================
        $shippings = [
            'air' => [
                'normal'  => $delhiveryService->getShippingCostBlock($baseQuery, 'E', 'normal'),
                'express' => $delhiveryService->getShippingCostBlock($baseQuery, 'E', 'express'),
            ],
            'surface' => [
                'normal'  => $delhiveryService->getShippingCostBlock($baseQuery, 'S', 'normal'),
                'express' => $delhiveryService->getShippingCostBlock($baseQuery, 'S', 'express'),
            ],
        ];

        // If EVERYTHING failed, return failure.
        $allFailed = true;
        foreach ($shippings as $mode) {
            foreach ($mode as $tier) {
                if (!empty($tier['ok'])) {
                    $allFailed = false;
                    break 2;
                }
            }
        }

        if ($allFailed) {
            return response()->json([
                'code'    => 400,
                'success' => false,
                'message' => 'Delhivery shipping cost fetch failed for all modes.',
                'through' => $through,
                'data'    => ['shippings' => $shippings],
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Shipping cost fetched.',
            'through' => $through,
            'data'    => [
                'shippings' => $shippings,
            ],
        ]);
    }

    /**
     * Delhivery API wants grams (cgm).
     * If user gives 1 (kg) => 1000 grams
     * If someone passes already big number (e.g., 1200) we treat it as grams.
     */
    private function toDelhiveryGrams($weightInput): int
    {
        $w = (float) $weightInput;

        if ($w <= 0) return 1000;

        // Heuristic:
        // - If <= 50 => treat as KG and convert to grams
        // - If > 50 => likely already grams
        if ($w <= 50) {
            return (int) round($w * 1000);
        }

        return (int) round($w);
    }


    // Create shipment
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
    // Create shipment by order id and pickup location id
    public function createShipByOrder(Request $request)
    {
        // 1) Validate only order_id – everything else is auto-fetched
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:t_orders,id',
            'pickup_location_id' => 'nullable|integer|exists:t_pickup_location,id',
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
            // ✅ Delhivery safe default dimensions (in cm)
            $shipmentLength = 10;
            $shipmentWidth  = 10;
            $shipmentHeight = 10;

            $productsDescription = implode(', ', $descParts);

            // Resolve pickup location:
            $pickupLocationId = $request->input('pickup_location_id');
            $pickup = null;

            // If pickup_location_id explicitly provided, try loading it
            if (!empty($pickupLocationId)) {
                $pickup = PickupLocationModel::find($pickupLocationId);
            }

            // If not provided OR not found, find default pickup
            if (!$pickup) {
                $pickup = PickupLocationModel::where('is_default', 1)
                    ->where('is_active', 1)
                    ->first();
            }

            // If still not found, pick first active pickup
            if (!$pickup) {
                $pickup = PickupLocationModel::where('is_active', 1)
                    ->orderBy('id')
                    ->first();
            }

            // If STILL no pickup → throw error
            if (!$pickup) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid pickup location found. Please configure t_pickup_location.',
                    'data'    => [],
                ], 500);
            }

            // Use courier_pickup_name if set, else internal name
            $pickupName    = $pickup->courier_pickup_name ?: $pickup->name;
            $pickupAddress = trim(
                $pickup->address_line1
                . ($pickup->address_line2 ? ', '.$pickup->address_line2 : '')
                . ($pickup->landmark ? ', '.$pickup->landmark : '')
            );
            $pickupPin     = $pickup->pin;
            $pickupCity    = $pickup->city;
            $pickupState   = $pickup->state;
            $pickupPhone   = $pickup->phone ?: $pickup->alternate_phone;

            // 5) Map shipping details from single shipping_address string
            // New format: name, mobile, city, state, country, pin, add1, add2
            $shippingAddress = $order->shipping_address;

            // Defaults
            $pin     = null;
            $city    = null;
            $state   = null;
            $country = null;
            $nameFromAddress  = null;
            $phoneFromAddress = null;

            // We'll also build a cleaner "address only" (without name/phone)
            $addressOnly = null;

            if ($shippingAddress) {
                // Split by comma and trim
                $parts = array_map('trim', explode(',', $shippingAddress));
                // Remove empty values and reindex
                $parts = array_values(array_filter($parts, fn($v) => $v !== ''));

                $count = count($parts);

                // Expected: [0] name, [1] mobile, [2] city, [3] state, [4] country, [5] pin, [6+] address lines
                if ($count >= 6) {
                    $nameFromAddress  = $parts[0] ?? null;
                    $phoneFromAddress = $parts[1] ?? null;

                    $city    = $parts[2] ?? null;
                    $state   = $parts[3] ?? null;
                    $country = $parts[4] ?? null;
                    $maybePin= $parts[5] ?? null;

                    // Validate pin as 6-digit pincode
                    if ($maybePin && preg_match('/^\d{6}$/', $maybePin)) {
                        $pin = $maybePin;
                    }

                    // Build address-only (add1, add2, etc.)
                    if ($count > 6) {
                        $addressOnly = implode(', ', array_slice($parts, 6));
                    }
                }
            }

            // Prefer parsed values, but fallback to user fields if needed
            if (!$pin)   { $pin   = $user->pin   ?? null; }
            if (!$city)  { $city  = $user->city  ?? null; }
            if (!$state) { $state = $user->state ?? null; }

            // If STILL missing critical pieces, error out
            if (!$pin || !$city || !$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing shipping pincode/city/state for this order. Please complete address first.',
                    'data'    => [
                        'shipping_address' => $shippingAddress,
                        'pin'              => $pin,
                        'city'             => $city,
                        'state'            => $state,
                    ],
                ], 422);
            }

            // Final phone: prefer mobile from address, then user->mobile
            $finalPhone = $phoneFromAddress ?: $user->mobile ?: null;

            // Optional: build a cleaner address string for Delhivery
            // (only street + city + state + pin + country; no name/phone)
            if ($addressOnly) {
                $shippingAddress = $addressOnly . ', ' . $city . ' - ' . $pin . ', ' . $state . ', ' . ($country ?: 'India');
            }

            // 6) Payment mode from order.payment_status
            // tweak mapping as per your logic
            $paymentMode = $order->payment_status === 'paid' ? 'Prepaid' : 'COD';
            $codAmount   = $paymentMode === 'COD' ? (float) $order->total_amount : 0.0;

            // Seller can still come from config
            $sellerName    = config('shipping.seller_name', 'Your Store Name');
            $sellerAddress = config('shipping.seller_address');
            if (!$sellerAddress) {
                $sellerAddress = $pickupAddress; // ✅ safest fallback
            }

            $sellerInvoice = 'INV-' . $order->id; // or $order->invoice_no etc.

            // 

            // 8) Build payload for DelhiveryService->placeOrder()
            $orderData = [
                // Customer
                'customer_name'        => $user->name,
                'customer_address'     => $shippingAddress,
                'pin'                  => $pin,
                'city'                 => $city,
                'state'                => $state,
                'phone'                => $finalPhone,

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

                // ✅ Dimensions (required by many Delhivery accounts)
                'shipment_length'      => $shipmentLength,
                'shipment_width'       => $shipmentWidth,
                'shipment_height'      => $shipmentHeight,

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
            $shipment->user_id              = $order->user_id;
            $shipment->courier              = 'delhivery';
            $shipment->pickup_location_id   = $pickup->id;   // store pickup id here
            $shipment->customer_name        = $orderData['customer_name'];
            $shipment->customer_phone       = $finalPhone;
            $shipment->customer_email       = $user->email;
            $shipment->shipping_address     = $orderData['customer_address'];
            $shipment->shipping_pin         = $orderData['pin'];
            $shipment->shipping_city        = $orderData['city'];
            $shipment->shipping_state       = $orderData['state'];
            $shipment->payment_mode         = $orderData['payment_mode'];
            $shipment->total_amount         = $orderData['total_amount'];
            $shipment->cod_amount           = $orderData['cod_amount'];
            $shipment->quantity             = $orderData['quantity'];
            $shipment->weight               = $orderData['weight'];
            $shipment->products_description = $orderData['products_description'];
            $shipment->pickup_name          = $orderData['pickup_name'];
            $shipment->pickup_address       = $orderData['pickup_address'];
            $shipment->pickup_pin           = $orderData['pickup_pin'];
            $shipment->pickup_city          = $orderData['pickup_city'];
            $shipment->pickup_state         = $orderData['pickup_state'];
            $shipment->pickup_phone         = $orderData['pickup_phone'];
            $shipment->request_payload      = $orderData;
            $shipment->response_payload     = $apiResponse;

            // If Delhivery returned an error (success=false or error flag)
            $isError = (($apiResponse['success'] ?? null) === false)
                    || (!empty($apiResponse['error']));

            if ($isError) {
                $msg = $apiResponse['rmk']
                    ?? (is_string($apiResponse['error']) ? $apiResponse['error'] : 'Delhivery returned an error.');

                $shipment->status        = 'failed';
                $shipment->error_message = $msg;
                $shipment->save();

                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'data'    => $apiResponse,
                ], 400);
            }

            // Extract AWB / waybill – adjust keys based on your actual response
            $awbNo   = $apiResponse['packages'][0]['waybill'] ?? null;
            $refNum  = $apiResponse['packages'][0]['refnum']  ?? null;

            $shipment->awb_no            = $awbNo;
            $shipment->courier_reference = $refNum;
            $shipment->status            = 'booked';
            $shipment->booked_at         = Carbon::now();
            $shipment->error_message     = null;
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

    public function checkShipment(Request $request)
    {
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

        $orderId = (int)$request->order_id;

        try {
            // Build the shipment data
            $data = $this->buildShipmentData($orderId);  // This returns the necessary data for shipment creation

            // Return the payload in the same structure as punchShipment API
            $response = [
                'order_id' => $orderId,
                'payload'  => $data['orderData']  // All the shipment data in the payload
            ];

            return response()->json([
                'success' => true,
                'message' => 'Shipment data fetched for review.',
                'data'    => $response, // return the data as needed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipment data: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }
    
    public function punchShipment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:t_orders,id',
            'payload'  => 'required|array',  // This is the same payload you get from `check_shipment` API
            'payload.courier'        => 'nullable|string',
            'payload.shipping_mode'  => 'nullable|in:air,surface',
            'payload.service_level'  => 'nullable|in:normal,express',
            'payload.address_type'   => 'nullable|in:home,work',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $orderId = (int)$request->order_id;
        $payload = $request->input('payload');  // Get the payload from frontend (updated or not)
        $payload['order_no'] = (string) $orderId;
        // ✅ Normalize incoming values (user-friendly -> Delhivery expected)
        $shippingMode = strtolower(trim($payload['shipping_mode'] ?? 'surface')); // air/surface
        $serviceLevel = strtolower(trim($payload['service_level'] ?? 'normal'));  // normal/express

        $payload['shipping_mode'] = ($shippingMode === 'air') ? 'Express' : 'Surface';
        $payload['service_level'] = ($serviceLevel === 'express') ? 'express' : 'normal';

        try {
            // 1. Update the database with any changes from the payload (if needed)
            $shipment = OrderShipment::firstOrNew(['order_id' => $orderId]);

            // Update or create the shipment with all the relevant data
            $shipment->fill([
                'order_id'             => $orderId,
                // 'user_id'              => $payload['user_id'] ?? null,
                'courier' => !empty($payload['courier']) ? strtolower(trim($payload['courier'])) : 'delhivery',
                // 'status'               => $payload['status'] ?? null,
                // 'customer_email'       => $payload['customer_email'] ?? null,
                // 'pickup_location_id'   => $payload['pickup_location_id'] ?? null,

                // Customer details
                'customer_name'        => $payload['customer_name'] ?? null,
                'shipping_address'     => $payload['customer_address'] ?? null,                
                'shipping_pin'         => $payload['pin'] ?? null,
                'shipping_city'        => $payload['city'] ?? null,
                'shipping_state'       => $payload['state'] ?? null,
                'customer_phone'       => $payload['phone'] ?? null,

                // Pickup Details
                'pickup_name'          => $payload['pickup_name'] ?? null,
                'pickup_address'       => $payload['pickup_address'] ?? null,
                'pickup_pin'           => $payload['pickup_pin'] ?? null,
                'pickup_city'          => $payload['pickup_city'] ?? null,
                'pickup_state'         => $payload['pickup_state'] ?? null,
                'pickup_phone'         => $payload['pickup_phone'] ?? null,

                // Return address
                'return_pin'           => $payload['return_pin'] ?? null,
                'return_city'          => $payload['return_city'] ?? null,
                'return_state'         => $payload['return_state'] ?? null,
                'return_phone'         => $payload['return_phone'] ?? null,
                'return_address'       => $payload['return_address'] ?? null,
                'return_country'       => $payload['return_country'] ?? null,

                // Seller Details
                'seller_name'          => $payload['seller_name'] ?? null,
                'seller_address'       => $payload['seller_address'] ?? null,
                'seller_invoice'       => $payload['seller_invoice'] ?? null,

                // Products
                'products_description' => $payload['products_description'] ?? null,
                'quantity'             => $payload['quantity'] ?? null,
                'weight'               => $payload['weight'] ?? null,
                'shipment_length'      => $payload['shipment_length'] ?? null,
                'shipment_width'       => $payload['shipment_width'] ?? null,
                'shipment_height'      => $payload['shipment_height'] ?? null,

                // Payments & others
                'payment_mode'         => $payload['payment_mode'] ?? null,
                'total_amount'         => $payload['total_amount'] ?? null,
                'cod_amount'           => $payload['cod_amount'] ?? null,
                'shipping_mode'        => $payload['shipping_mode'] ?? null,
                'service_level'        => $payload['service_level'] ?? null,
                'address_type'         => $payload['address_type'] ?? null,
                                
                // Resposne get
                'awb_no'               => null,  // AWB number if returned from Delhivery
                'courier_reference'    => $payload['courier_reference'] ?? null,
                'request_payload'      => $payload,  // Save the entire payload for reference
                'response_payload'     => null,
                'error_message'        => $payload['error_message'] ?? null,
                
            ]);

            $shipment->save();  // Save to DB

            // 2. Now send the updated payload to Delhivery API to create the shipment
            $delhiveryService = new DelhiveryService();
            $apiResponse = $delhiveryService->placeOrder($payload); // Send the payload to Delhivery

            // If Delhivery returned an error, log it and return failure
            $isError = isset($apiResponse['error']) || isset($apiResponse['rmk']);
            if ($isError) {
                return response()->json([
                    'success' => false,
                    'message' => $apiResponse['rmk'] ?? 'Error from Delhivery',
                    'data'    => $apiResponse,
                ], 400);
            }

            // 3. Update the shipment data with Delhivery's response (e.g., tracking number, waybill)
            $waybill = data_get($apiResponse, 'packages.0.waybill');

            if (!$waybill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delhivery response did not include waybill.',
                    'data'    => $apiResponse,
                ], 400);
            }

            $shipment->awb_no = $waybill;
            $shipment->status = 'booked';
            $shipment->response_payload = $apiResponse;
            $shipment->save();


            return response()->json([
                'success' => true,
                'message' => 'Shipment successfully booked with Delhivery.',
                'data'    => $apiResponse,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unexpected error while punching shipment: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }
    // Build the shipment data for both checking and punching
    private function buildShipmentData(int $orderId, ?int $pickupLocationId = null, array $overrides = []): array
    {
        $order = OrderModel::with('user')->findOrFail($orderId);
        $user = $order->user;
        if (!$user) throw new \Exception("User not found for this order.");

        // Items
        $items = OrderItemModel::with(['product:id,name', 'variant:id,weight'])
            ->where('order_id', $order->id)
            ->get();

        if ($items->isEmpty()) throw new \Exception("No items found for this order.");

        $descParts = [];
        $totalQty = 0;
        $totalWeight = 0.0;

        foreach ($items as $item) {
            $name = optional($item->product)->name ?? ('Product #'.$item->product_id);
            $qty  = (int)$item->quantity;

            $descParts[] = $name.' x '.$qty;
            $totalQty += $qty;

            $variantWeight = optional($item->variant)->weight;
            if (!is_null($variantWeight)) {
                $totalWeight += ((float)$variantWeight) * $qty;
            }
        }

        if ($totalWeight <= 0) $totalWeight = 1.0;
        $productsDescription = implode(', ', $descParts);

        // Pickup resolve
        $pickup = null;
        if (!empty($pickupLocationId)) {
            $pickup = PickupLocationModel::find($pickupLocationId);
        }
        if (!$pickup) {
            $pickup = PickupLocationModel::where('is_default', 1)->where('is_active', 1)->first();
        }
        if (!$pickup) {
            $pickup = PickupLocationModel::where('is_active', 1)->orderBy('id')->first();
        }
        if (!$pickup) throw new \Exception("No valid pickup location found.");

        $pickupName = $pickup->courier_pickup_name ?: $pickup->name;
        $pickupAddress = trim(
            $pickup->address_line1
            . ($pickup->address_line2 ? ', '.$pickup->address_line2 : '')
            . ($pickup->landmark ? ', '.$pickup->landmark : '')
        );

        // Shipping address parse (your new format)
        $shippingAddressRaw = $order->shipping_address;

        $pin = $city = $state = $country = null;
        $nameFromAddress = $phoneFromAddress = null;
        $addressOnly = null;

        if ($shippingAddressRaw) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $shippingAddressRaw)), fn($v) => $v !== ''));
            if (count($parts) >= 6) {
                $nameFromAddress  = $parts[0] ?? null;
                $phoneFromAddress = $parts[1] ?? null;
                $city    = $parts[2] ?? null;
                $state   = $parts[3] ?? null;
                $country = $parts[4] ?? null;
                $maybePin= $parts[5] ?? null;
                if ($maybePin && preg_match('/^\d{6}$/', $maybePin)) $pin = $maybePin;
                if (count($parts) > 6) $addressOnly = implode(', ', array_slice($parts, 6));
            }
        }

        if (!$pin)   $pin   = $user->pin   ?? null;
        if (!$city)  $city  = $user->city  ?? null;
        if (!$state) $state = $user->state ?? null;

        if (!$pin || !$city || !$state) throw new \Exception("Missing shipping pin/city/state.");

        $finalPhone = $phoneFromAddress ?: $user->mobile ?: null;
        $cleanAddress = $addressOnly
            ? ($addressOnly . ', ' . $city . ' - ' . $pin . ', ' . $state . ', ' . ($country ?: 'India'))
            : $shippingAddressRaw;

        // Payment mapping (IMPORTANT: use Prepaid/COD)
        $paymentMode = $order->payment_status === 'paid' ? 'Prepaid' : 'COD';
        $codAmount   = $paymentMode === 'COD' ? (float)$order->total_amount : 0.0;

        // Seller (prefer config; fallback pickup)
        $sellerName = config('shipping.seller_name', 'Your Store Name');
        $sellerAddress = config('shipping.seller_address') ?: $pickupAddress;
        $sellerInvoice = 'INV-' . $order->id;

        // Defaults
        $orderData = [
            'customer_name'        => $user->name,
            'customer_address'     => $cleanAddress,
            'pin'                  => $pin,
            'city'                 => $city,
            'state'                => $state,
            'phone'                => $finalPhone,

            'order_no'             => (string)$order->id,
            'payment_mode'         => $paymentMode,
            'total_amount'         => (float)$order->total_amount,
            'cod_amount'           => $codAmount,

            'products_description' => $productsDescription,
            'quantity'             => $totalQty,
            'weight'               => round($totalWeight, 3),
            'order_date'           => $order->created_at ? $order->created_at->toDateString() : Carbon::now()->toDateString(),

            'seller_name'          => $sellerName,
            'seller_address'       => $sellerAddress,
            'seller_invoice'       => $sellerInvoice,

            'pickup_name'          => $pickupName,
            'pickup_address'       => $pickupAddress,
            'pickup_pin'           => $pickup->pin,
            'pickup_city'          => $pickup->city,
            'pickup_state'         => $pickup->state,
            'pickup_phone'         => $pickup->phone ?: $pickup->alternate_phone,

            'shipment_length'      => 10,
            'shipment_width'       => 10,
            'shipment_height'      => 10,

            'shipping_mode'        => 'Surface',
            'service_level'        => 'normal',
            'address_type'         => 'home',

            'return_pin'           => $pickup->pin,
            'return_city'          => $pickup->city,
            'return_phone'         => $pickup->phone ?: $pickup->alternate_phone,
            'return_address'       => $pickupAddress,
            'return_state'         => $pickup->state,
            'return_country'       => 'India',
        ];

        // ✅ Apply overrides (allow only safe keys)
        $allowed = [
            'customer_name','customer_address','phone',
            'shipping_mode','service_level','address_type',
            'shipment_length','shipment_width','shipment_height',
        ];

        foreach ($allowed as $k) {
            if (array_key_exists($k, $overrides) && $overrides[$k] !== null && $overrides[$k] !== '') {
                $orderData[$k] = $overrides[$k];
            }
        }

        // Return full preview pack
        return [
            'order'      => $order,
            'user'       => $user,
            'pickup'     => $pickup,
            'items'      => $items,
            'orderData'  => $orderData,
        ];
    }

    // fetch delivery details from db
    public function fetchShipment(Request $request, $order_id = null)
    {
        try {
            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);

            if ($limit <= 0) $limit = 10;
            if ($offset < 0) $offset = 0;

            // Body params
            $shipmentId = $request->input('id');            // shipment primary ID
            $search     = $request->input('search');
            $courier    = $request->input('courier');
            $status     = $request->input('status');
            $awbNo      = $request->input('awb_no');         // optional AWB filter

            $query = OrderShipment::query();

            // 🔹 Filter by ORDER ID (URL param)
            if ($order_id) {
                $query->where('order_id', $order_id);
            }

            // 🔹 Filter by SHIPMENT ID (BODY)
            if ($shipmentId) {
                $query->where('id', $shipmentId);
            }

            // Courier filter (body)
            if (!empty($courier)) {
                $query->where('courier', $courier);
            }

            // Status filter (body)
            if (!empty($status)) {
                $query->where('status', $status);
            }

            // AWB filter
            if (!empty($awbNo)) {
                $query->where('awb_no', 'like', '%' . $awbNo . '%');
            }

            // Generic search box
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('awb_no', 'like', "%{$search}%")
                    ->orWhere('courier_reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('shipping_pin', 'like', "%{$search}%");
                });
            }

            // If ONLY a single shipment ID is given → return single row
            if ($shipmentId) {
                $shipment = $query->first();

                if (!$shipment) {
                    return response()->json([
                        'code' => 404,
                        'success' => false,
                        'message' => 'Shipment not found.',
                        'data' => []
                    ], 200);
                }

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Shipment fetched successfully.',
                    'data' => $shipment,
                    'records' => 1,
                    'count_perpage' => 1
                ]);
            }

            // Pagination for list
            $totalRecords = $query->count();

            $shipments = $query
                ->orderBy('id', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'Shipping details fetched successfully.',
                'data'          => $shipments,
                'records'       => $totalRecords,
                'count_perpage' => $limit
            ]);
            
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    public function trackShipments(Request $request)
    {
        // From JSON/body: can be "6" or "6,8,10"
        $orderIdRaw  = $request->input('order_id');   // optional
        $waybillRaw  = $request->input('waybill');    // optional

        if (empty($orderIdRaw) && empty($waybillRaw)) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Either order_id or waybill is required in the request body.',
                'data'    => [],
            ], 422);
        }

        $waybillList = [];

        /* ---------------------------------------------
         * 1. Collect AWBs from order_id(s)
         * ------------------------------------------- */
        if (!empty($orderIdRaw)) {
            // Allow either string "6,8,10" or array [6,8,10]
            $orderIds = [];
            if (is_array($orderIdRaw)) {
                $orderIds = $orderIdRaw;
            } else {
                // "6,8,10" -> [6,8,10]
                $orderIds = array_filter(array_map('trim', explode(',', $orderIdRaw)));
            }

            // Keep only numeric IDs
            $orderIds = array_values(array_filter($orderIds, function ($id) {
                return is_numeric($id);
            }));

            if (!empty($orderIds)) {
                $awbNumbers = OrderShipment::whereIn('order_id', $orderIds)
                    ->whereNotNull('awb_no')
                    ->pluck('awb_no')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $waybillList = array_merge($waybillList, $awbNumbers);
            }
        }

        /* ---------------------------------------------
         * 2. Collect direct waybills from "waybill"
         * ------------------------------------------- */
        if (!empty($waybillRaw)) {
            // Allow string "awb1,awb2" or array
            $waybillFromBody = [];
            if (is_array($waybillRaw)) {
                $waybillFromBody = $waybillRaw;
            } else {
                $waybillFromBody = array_filter(array_map('trim', explode(',', $waybillRaw)));
            }

            if (!empty($waybillFromBody)) {
                $waybillList = array_merge($waybillList, $waybillFromBody);
            }
        }

        // Clean & dedupe final list
        $waybillList = array_values(array_unique(array_filter($waybillList)));

        if (empty($waybillList)) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'No valid AWB/waybill numbers found for the given input.',
                'data'    => [],
            ], 404);
        }

        $delhiveryService = new DelhiveryService();

        try {
            $response = $delhiveryService->trackShipments($waybillList);
        } catch (\Exception $e) {
            Log::error('Delhivery trackShipments exception: ' . $e->getMessage());

            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to fetch shipment tracking.',
                'data'    => [],
            ], 500);
        }

        if (isset($response['error'])) {
            return response()->json([
                'code'    => 400,
                'success' => false,
                'message' => $response['error'],
                'data'    => [],
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Shipment tracking fetched.',
            'data'    => $response,
        ]);
    }
    public function getTat(Request $request)
    {
        $through = $request->input('through');

        // 1) Validate "through" first
        if (!in_array($through, ['order', 'simple', 'product'], true)) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Invalid "through" value. Allowed: order, simple, product.',
                'data'    => [],
            ], 422);
        }

        /* -------------------------------------------------
         * 2) Conditional validation based on "through"
         * ------------------------------------------------- */

        if ($through === 'order') {
            // order mode: only order_id required
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|integer|exists:t_orders,id',
            ]);
        } elseif ($through === 'simple') {
            // simple mode: same as your original, but via POST body
            $validator = Validator::make($request->all(), [
                'origin_pin'         => 'required|digits:6',
                'destination_pin'    => 'required|digits:6',
                'mot'                => 'required|in:S,E',           // Surface / Express
                'pdt'                => 'nullable|in:B2B,B2C',
                'expected_pickup_date' => 'nullable|string',         // you can change to date_format if needed
            ]);
        } else { // through === 'product'
            // product mode: only destination_pin required
            $validator = Validator::make($request->all(), [
                'destination_pin' => 'required|digits:6',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        /* -------------------------------------------------
         * 3) Derive final parameters by mode
         * ------------------------------------------------- */

        $originPin      = null;
        $destinationPin = null;
        $mot            = null;
        $pdt            = null;
        $expectedPickup = null;

        if ($through === 'order') {
            // ---------- MODE: order ----------
            $orderId = (int) $request->input('order_id');

            // Fetch order
            $order = OrderModel::find($orderId);
            if (!$order) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Order not found.',
                    'data'    => [],
                ], 404);
            }

            // Fetch shipment (latest for this order)
            $shipment = OrderShipment::where('order_id', $orderId)
                ->orderByDesc('id')
                ->first();

            if (!$shipment || empty($shipment->pickup_pin) || empty($shipment->shipping_pin)) {
                return response()->json([
                    'code'    => 400,
                    'success' => false,
                    'message' => 'Shipment pins not found for this order.',
                    'data'    => [],
                ], 400);
            }

            $originPin      = (string) $shipment->pickup_pin;   // from t_order_shipments.pickup_pin
            $destinationPin = (string) $shipment->shipping_pin; // from t_order_shipments.shipping_pin
            $mot            = 'E';                              // always
            $pdt            = 'B2C';                            // always

            // order created_at as expected_pickup_date
            $expectedPickup = $order->created_at
                ? $order->created_at->format('Y-m-d H:i')
                : now()->format('Y-m-d H:i');

        } elseif ($through === 'simple') {
            // ---------- MODE: simple ----------
            $originPin      = $request->input('origin_pin');
            $destinationPin = $request->input('destination_pin');
            $mot            = $request->input('mot', 'S');
            $pdt            = $request->input('pdt'); // optional (B2B/B2C)
            $expectedPickupRaw = $request->input('expected_pickup_date');

            if (!empty($expectedPickupRaw)) {
                try {
                    $expectedPickup = \Carbon\Carbon::parse($expectedPickupRaw)->format('Y-m-d H:i');
                } catch (\Exception $e) {
                    return response()->json([
                        'code'    => 422,
                        'success' => false,
                        'message' => "Invalid expected_pickup_date. Please send a parseable date/time.",
                        'data'    => [],
                    ], 422);
                }
            } else {
                $expectedPickup = null;
            }


        } else {
            // ---------- MODE: product ----------
            // destination_pin from body
            $destinationPin = $request->input('destination_pin');
            // origin_pin fixed
            $originPin      = '713146';
            // mot & pdt always
            $mot            = 'E';
            $pdt            = 'B2C';
            // expected_pickup_date = current fetch time
            $expectedPickup = now()->format('Y-m-d H:i');

        }

        /* -------------------------------------------------
         * 4) Call service
         * ------------------------------------------------- */

        $delhiveryService = new DelhiveryService();
        $response = $delhiveryService->getTat(
            $originPin,
            $destinationPin,
            $mot,
            $pdt,
            $expectedPickup
        );

        if (isset($response['error'])) {
            return response()->json([
                'code'    => 400,
                'success' => false,
                'message' => $response['error'],
                'data'    => $response['raw'] ?? [],
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'TAT fetched successfully.',
            'data'    => $response,
        ]);
    }
    
    // pickup details :
    public function createPickupLocation(Request $request)
    {
        // 1) Validate input
        $validator = Validator::make($request->all(), [
            'name'                => 'required|string|max:255',   // internal name
            'code'                => 'nullable|string|max:100',
            'courier_pickup_name' => 'nullable|string|max:255',   // warehouse name in Delhivery (e.g. "Burhanuddin")
            'courier_pickup_code' => 'nullable|string|max:100',

            'contact_person'      => 'nullable|string|max:255',
            'phone'               => 'required|string|max:20',
            'alternate_phone'     => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',

            'address_line1'       => 'required|string|max:255',
            'address_line2'       => 'nullable|string|max:255',
            'landmark'            => 'nullable|string|max:255',

            'city'                => 'required|string|max:100',
            'district'            => 'nullable|string|max:100',
            'state'               => 'required|string|max:100',
            'pin'                 => 'required|string|max:10',    // can be digits:6 if always Indian PIN
            'country'             => 'nullable|string|max:50',

            'is_default'          => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Normalize flags
        $data['is_default'] = isset($data['is_default']) ? (bool)$data['is_default'] : false;
        $data['is_active']  = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $data['country']    = $data['country'] ?? 'India';

        // 2) If this is marked as default, unset default on others
        if ($data['is_default']) {
            PickupLocationModel::where('is_default', 1)->update(['is_default' => 0]);
        }

        // 3) Create pickup location in DB
        $pickup = PickupLocationModel::create($data);
        $pickup->makeHidden(['created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'message' => 'Pickup location created successfully.',
            'data'    => $pickup,
        ], 201);
    }
    public function fetchPickupLocations(Request $request, $id = null)
    {
        // If ID is passed in the URL => return single record
        if ($id !== null) {
            $pickup = PickupLocationModel::find($id);

            if (!$pickup) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pickup location not found.',
                    'data'    => [],
                ], 404);
            }

            // Hide timestamps
            $pickup->makeHidden(['created_at', 'updated_at']);

            return response()->json([
                'success' => true,
                'message' => 'Pickup location fetched successfully.',
                'data'    => $pickup,
            ]);
        }

        // Otherwise => list with filters from body

        $validator = Validator::make($request->all(), [
            'is_active' => 'nullable|in:0,1',       // filter by active
            'default'   => 'nullable|in:0,1',       // maps to is_default
            'name'      => 'nullable|string',       // search in name / courier_pickup_name
            'pincode'   => 'nullable|string',       // search in pin

            'limit'     => 'nullable|integer|min:1|max:200',
            'offset'    => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'data'    => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        $limit  = $filters['limit']  ?? 50;
        $offset = $filters['offset'] ?? 0;

        $query = PickupLocationModel::query();

        // is_active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', (int) $filters['is_active']);
        }

        // default filter (maps to is_default)
        if (isset($filters['default'])) {
            $query->where('is_default', (int) $filters['default']);
        }

        // name filter (name + courier_pickup_name)
        if (!empty($filters['name'])) {
            $name = $filters['name'];
            $query->where(function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%")
                ->orWhere('courier_pickup_name', 'like', "%{$name}%");
            });
        }

        // pincode filter
        if (!empty($filters['pincode'])) {
            $query->where('pin', 'like', "%{$filters['pincode']}%");
        }

        // Order: default first, then by name
        $query->orderByDesc('is_default')
            ->orderBy('name');

        // Total BEFORE limit/offset
        $total = $query->count();

        // Apply limit/offset
        $items = $query->skip($offset)
                    ->take($limit)
                    ->get();

        // Hide timestamps on each item
        $items->makeHidden(['created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'message' => 'Pickup locations fetched successfully.',
            'data'    => [
                'items' => $items->values(),   // reset index
                'meta'  => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'total'  => $total,
                ],
            ],
        ]);
    }
    public function deletePickupLocation($id)
    {
        $pickup = PickupLocationModel::find($id);

        if (!$pickup) {
            return response()->json([
                'success' => false,
                'message' => 'Pickup location not found.',
                'data'    => [],
            ], 404);
        }

        // Protect default pickup (optional)
        // If you want to prevent deleting default pickup, uncomment:
        /*
        if ($pickup->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Default pickup location cannot be deleted.',
                'data'    => [],
            ], 400);
        }
        */

        $pickup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pickup location deleted successfully.',
            'data'    => ['id' => $id],
        ]);
    }
}