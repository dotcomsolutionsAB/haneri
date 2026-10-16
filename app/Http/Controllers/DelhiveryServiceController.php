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

            // Optional box size. Charged weight uses the higher of actual and volumetric.
            'length'  => 'nullable|numeric|min:1',
            'breadth' => 'nullable|numeric|min:1',
            'height'  => 'nullable|numeric|min:1',
            'pickup_name' => 'nullable|string|max:255',
            'packages' => 'nullable|array|min:1',
            'packages.*.length'  => 'required_with:packages|numeric|min:1',
            'packages.*.breadth' => 'required_with:packages|numeric|min:1',
            'packages.*.height'  => 'required_with:packages|numeric|min:1',
            'packages.*.weight'  => 'required_with:packages|numeric|min:0.01',
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
        $lengthCm = 10.0;
        $breadthCm = 10.0;
        $heightCm = 10.0;
        $packageBreakdown = [];

        if ($through === 'order') {
            $orderId = (int) $request->input('order_id');

            try {
                $overrides = [];
                if ($request->filled('pickup_name')) {
                    $overrides['pickup_name'] = $request->input('pickup_name');
                }
                $orderData = $this->buildShipmentData($orderId, $overrides)['orderData'];
            } catch (\Exception $e) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data'    => [],
                ], 422);
            }

            $originPin      = $orderData['pickup_pin'] ?? null;
            $destinationPin = $orderData['pin'] ?? null;

            if (!$originPin || !$destinationPin) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'Pickup pin / shipping pin missing for this order.',
                    'data'    => [],
                ], 422);
            }

            $requestPackages = $request->input('packages');
            if (is_array($requestPackages) && count($requestPackages) > 0) {
                foreach ($requestPackages as $pkg) {
                    $packageBreakdown[] = [
                        'length'  => max(1, (float) ($pkg['length'] ?? 10)),
                        'breadth' => max(1, (float) ($pkg['breadth'] ?? 10)),
                        'height'  => max(1, (float) ($pkg['height'] ?? 10)),
                        'weight'  => max(0.01, (float) ($pkg['weight'] ?? 1)),
                    ];
                }
            } elseif ($request->filled('weight') || $request->filled('length')) {
                $packageBreakdown[] = [
                    'length'  => max(1, (float) ($request->input('length', $orderData['shipment_length'] ?? 10))),
                    'breadth' => max(1, (float) ($request->input('breadth', $orderData['shipment_width'] ?? 10))),
                    'height'  => max(1, (float) ($request->input('height', $orderData['shipment_height'] ?? 10))),
                    'weight'  => max(0.01, (float) ($request->filled('weight')
                        ? $request->input('weight')
                        : ($orderData['weight'] ?? 1))),
                ];
            } else {
                foreach ((array) ($orderData['packages'] ?? []) as $pkg) {
                    $packageBreakdown[] = [
                        'length'  => max(1, (float) ($pkg['shipment_length'] ?? 10)),
                        'breadth' => max(1, (float) ($pkg['shipment_width'] ?? 10)),
                        'height'  => max(1, (float) ($pkg['shipment_height'] ?? 10)),
                        'weight'  => max(0.01, (float) ($pkg['weight'] ?? 1)),
                    ];
                }
                if ($packageBreakdown === []) {
                    $packageBreakdown[] = [
                        'length'  => max(1, (float) ($orderData['shipment_length'] ?? 10)),
                        'breadth' => max(1, (float) ($orderData['shipment_width'] ?? 10)),
                        'height'  => max(1, (float) ($orderData['shipment_height'] ?? 10)),
                        'weight'  => max(0.01, (float) ($orderData['weight'] ?? 1)),
                    ];
                }
            }
        } else {
            $originPin      = $request->input('origin_pin');
            $destinationPin = $request->input('destination_pin');
            $packageBreakdown[] = [
                'length'  => max(1, (float) $request->input('length', 10)),
                'breadth' => max(1, (float) $request->input('breadth', 10)),
                'height'  => max(1, (float) $request->input('height', 10)),
                'weight'  => max(0.01, (float) $request->input('weight')),
            ];
        }

        $actualKg = 0.0;
        $volumetricKg = 0.0;
        $chargedKg = 0.0;
        $details = [];
        foreach ($packageBreakdown as $pkg) {
            $lengthCm = $pkg['length'];
            $breadthCm = $pkg['breadth'];
            $heightCm = $pkg['height'];
            $pkgActual = $pkg['weight'];
            $pkgVolumetric = ($lengthCm * $breadthCm * $heightCm) / 5000;
            $pkgCharged = max($pkgActual, $pkgVolumetric);
            $actualKg += $pkgActual;
            $volumetricKg += $pkgVolumetric;
            $chargedKg += $pkgCharged;
            $details[] = [
                'length_cm' => round($lengthCm, 2),
                'breadth_cm' => round($breadthCm, 2),
                'height_cm' => round($heightCm, 2),
                'actual_weight_kg' => round($pkgActual, 3),
                'volumetric_weight_kg' => round($pkgVolumetric, 3),
                'charged_weight_kg' => round($pkgCharged, 3),
                'charged_on' => $pkgVolumetric > $pkgActual ? 'volumetric' : 'actual',
            ];
        }

        $first = $details[0] ?? [
            'length_cm' => 10,
            'breadth_cm' => 10,
            'height_cm' => 10,
        ];
        $package = [
            'length_cm' => $first['length_cm'],
            'breadth_cm' => $first['breadth_cm'],
            'height_cm' => $first['height_cm'],
            'actual_weight_kg' => round($actualKg, 3),
            'volumetric_weight_kg' => round($volumetricKg, 3),
            'charged_weight_kg' => round(max(0.01, $chargedKg), 3),
            'charged_on' => $volumetricKg > $actualKg ? 'volumetric' : 'actual',
            'packages' => $details,
            'package_count' => count($details),
        ];

        // Delhivery expects cgm in grams. Weight here is already kilograms.
        $cgm = (int) round(max(0.01, $chargedKg) * 1000);

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
                'data'    => ['shippings' => $shippings, 'package' => $package],
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Shipping cost fetched.',
            'through' => $through,
            'data'    => [
                'shippings' => $shippings,
                'package' => $package,
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
            'pickup_name' => 'nullable|string|max:255',
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

            // Pickup resolved against Delhivery warehouses in alignPickupWithDelhivery().
            $pickupName    = (string) ($request->input('pickup_name') ?: config('shipping.pickup.name', ''));
            $pickupAddress = (string) config('shipping.pickup.address', '');
            $pickupPin     = (string) config('shipping.pickup.pin', '');
            $pickupCity    = (string) config('shipping.pickup.city', '');
            $pickupState   = (string) config('shipping.pickup.state', '');
            $pickupPhone   = (string) config('shipping.pickup.phone', '');

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

            $seller = SettingController::sellerDetails();
            $sellerName = $seller['name'] !== '' ? $seller['name'] : 'Haneri Electricals LLP';
            $sellerAddress = $seller['address'] !== '' ? $seller['address'] : $pickupAddress;

            $existingShipment = OrderShipment::where('order_id', $order->id)->first();
            $clientOrderNo = $this->nextDelhiveryClientOrderNo($order->id, $existingShipment);
            $sellerInvoice = 'INV-' . $clientOrderNo;

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
                'order_no'             => $clientOrderNo,
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
            try {
                $orderData = $this->alignPickupWithDelhivery($orderData);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data'    => [],
                ], 422);
            }
            $delhiveryService = new DelhiveryService();
            $apiResponse      = $delhiveryService->placeOrder($orderData);

            // 10) Prepare / update shipment record in DB
            $shipment = OrderShipment::firstOrNew(['order_id' => $order->id]);
            $shipment->user_id              = $order->user_id;
            $shipment->courier              = 'delhivery';
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
                $msg = $this->delhiveryBookingFailure(is_array($apiResponse) ? $apiResponse : null)
                    ?? 'Delhivery returned an error.';

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
                'payload'  => $data['orderData'],
                'packages' => $data['packages'] ?? [],
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
            'payload.shipment_length' => 'nullable|numeric|min:1',
            'payload.shipment_width'  => 'nullable|numeric|min:1',
            'payload.shipment_height' => 'nullable|numeric|min:1',
            'payload.weight'          => 'nullable|numeric|min:0.01',
            'payload.packages'                    => 'nullable|array|min:1',
            'payload.packages.*.products_description' => 'nullable|string|max:500',
            'payload.packages.*.quantity'         => 'nullable|integer|min:1',
            'payload.packages.*.weight'           => 'required_with:payload.packages|numeric|min:0.01',
            'payload.packages.*.shipment_length'  => 'required_with:payload.packages|numeric|min:1',
            'payload.packages.*.shipment_width'   => 'required_with:payload.packages|numeric|min:1',
            'payload.packages.*.shipment_height'  => 'required_with:payload.packages|numeric|min:1',
            'payload.packages.*.total_amount'     => 'nullable|numeric|min:0',
            'payload.packages.*.cod_amount'       => 'nullable|numeric|min:0',
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
        try {
            $payload = $this->alignPickupWithDelhivery($payload);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
            ], 422);
        }
        // ✅ Normalize incoming values (user-friendly -> Delhivery expected)
        $shippingMode = strtolower(trim($payload['shipping_mode'] ?? 'surface')); // air/surface
        $serviceLevel = strtolower(trim($payload['service_level'] ?? 'normal'));  // normal/express

        $payload['shipping_mode'] = ($shippingMode === 'air') ? 'Express' : 'Surface';
        $payload['service_level'] = ($serviceLevel === 'express') ? 'express' : 'normal';

        // Keep top-level dims in sync with the first package for legacy columns.
        if (!empty($payload['packages']) && is_array($payload['packages'])) {
            $first = $payload['packages'][0] ?? null;
            if (is_array($first)) {
                $payload['shipment_length'] = $first['shipment_length'] ?? $payload['shipment_length'] ?? 10;
                $payload['shipment_width']  = $first['shipment_width'] ?? $payload['shipment_width'] ?? 10;
                $payload['shipment_height'] = $first['shipment_height'] ?? $payload['shipment_height'] ?? 10;
                $payload['weight'] = collect($payload['packages'])->sum(function ($pkg) {
                    return (float) ($pkg['weight'] ?? 0);
                });
                $payload['quantity'] = collect($payload['packages'])->sum(function ($pkg) {
                    return (int) ($pkg['quantity'] ?? 1);
                });
                $payload['products_description'] = collect($payload['packages'])
                    ->map(fn ($pkg) => (string) ($pkg['products_description'] ?? ''))
                    ->filter()
                    ->implode(', ') ?: ($payload['products_description'] ?? null);
            }
        }

        try {
            // 1. Update the database with any changes from the payload (if needed)
            $shipment = OrderShipment::firstOrNew(['order_id' => $orderId]);
            $previousWaybills = $shipment->exists
                ? $this->splitWaybills((string) ($shipment->awb_no ?? ''))
                : [];

            // Delhivery requires a fresh client order id after cancel; reusing 29-P1
            // returns the cancelled AWB instead of creating a new shipment.
            $payload['order_no'] = $this->nextDelhiveryClientOrderNo(
                $orderId,
                $shipment->exists ? $shipment : null
            );
            $payload['seller_invoice'] = 'INV-' . $payload['order_no'];
            $seller = SettingController::sellerDetails();
            if ($seller['name'] !== '') {
                $payload['seller_name'] = $seller['name'];
            }
            if ($seller['address'] !== '') {
                $payload['seller_address'] = $seller['address'];
            }

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

            // Attach per-line invoice amounts so each box does not get the full order total.
            $payload['packages'] = $this->enrichPackagesWithLineAmounts(
                $orderId,
                is_array($payload['packages'] ?? null) ? $payload['packages'] : [],
                (string) ($payload['payment_mode'] ?? 'Prepaid')
            );

            // 2. Now send the updated payload to Delhivery API to create the shipment
            $delhiveryService = new DelhiveryService();
            $apiResponse = $delhiveryService->placeOrder($payload); // Send the payload to Delhivery

            // Package remarks carry the real reason. rmk is often a generic internal-error line.
            $failure = $this->delhiveryBookingFailure(is_array($apiResponse) ? $apiResponse : null);
            if ($failure !== null) {
                $shipment->status = 'failed';
                $shipment->error_message = $failure;
                $shipment->response_payload = $apiResponse;
                $shipment->save();

                return response()->json([
                    'success' => false,
                    'message' => $failure,
                    'data'    => $apiResponse,
                ], 400);
            }

            // 3. Update the shipment data with Delhivery's response (e.g., tracking number, waybill)
            $waybills = $this->extractDelhiveryWaybills(is_array($apiResponse) ? $apiResponse : []);
            $masterWaybill = is_array($apiResponse)
                ? trim((string) ($apiResponse['master_waybill'] ?? ''))
                : '';
            if ($masterWaybill !== '' && !in_array($masterWaybill, $waybills, true)) {
                array_unshift($waybills, $masterWaybill);
            } elseif ($masterWaybill !== '') {
                // Keep master first for display / Cargo tracking.
                $waybills = array_values(array_unique(array_merge(
                    [$masterWaybill],
                    array_filter($waybills, static fn ($w) => $w !== $masterWaybill)
                )));
            }

            if ($waybills === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delhivery response did not include waybill.',
                    'data'    => $apiResponse,
                ], 400);
            }

            // Guard: Delhivery sometimes echoes cancelled AWBs when the client order id is reused.
            if (
                $previousWaybills !== []
                && count(array_diff($waybills, $previousWaybills)) === 0
            ) {
                $shipment->status = 'failed';
                $shipment->error_message = 'Delhivery returned the previous cancelled AWB(s). Retry booking to get a new waybill.';
                $shipment->response_payload = $apiResponse;
                $shipment->save();

                return response()->json([
                    'success' => false,
                    'message' => $shipment->error_message,
                    'data'    => [
                        'previous_awb' => $previousWaybills,
                        'returned_awb' => $waybills,
                        'order_no'     => $payload['order_no'],
                        'raw'          => $apiResponse,
                    ],
                ], 409);
            }

            $isMps = $masterWaybill !== ''
                || (is_array($apiResponse) && ($apiResponse['shipment_type'] ?? '') === 'MPS')
                || count($waybills) > 1;
            $shipment->awb_no = implode(', ', $waybills);
            $shipment->courier_reference = $masterWaybill !== '' ? $masterWaybill : ($waybills[0] ?? null);
            $shipment->status = 'booked';
            $shipment->booked_at = Carbon::now();
            $shipment->cancelled_at = null;
            $shipment->error_message = null;
            $shipment->response_payload = $apiResponse;
            $shipment->save();

            $message = $isMps
                ? ('Delhivery Cargo booked. Master AWB '
                    . ($masterWaybill !== '' ? $masterWaybill : $waybills[0])
                    . ' · ' . count($waybills) . ' packages.')
                : 'Shipment successfully booked with Delhivery.';

            return response()->json([
                'success' => true,
                'message' => $message,
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

    /**
     * Cancel Delhivery packages for an order while they are still ready to ship
     * (manifested / pending) — not after pickup has started.
     */
    public function cancelShipment(Request $request)
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

        $orderId = (int) $request->input('order_id');
        $shipment = OrderShipment::where('order_id', $orderId)->first();

        if (!$shipment || empty($shipment->awb_no)) {
            return response()->json([
                'success' => false,
                'message' => 'No booked Delhivery shipment found for this order.',
                'data'    => [],
            ], 404);
        }

        if (strtolower((string) $shipment->status) === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This shipment is already cancelled.',
                'data'    => [
                    'status' => $shipment->status,
                    'awb_no' => $shipment->awb_no,
                ],
            ], 422);
        }

        $waybills = $this->splitWaybills((string) $shipment->awb_no);
        if ($waybills === []) {
            return response()->json([
                'success' => false,
                'message' => 'No valid AWB numbers found on this shipment.',
                'data'    => [],
            ], 422);
        }

        $delhiveryService = new DelhiveryService();

        try {
            $track = $delhiveryService->trackShipments($waybills);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify package status with Delhivery: ' . $e->getMessage(),
                'data'    => [],
            ], 502);
        }

        $statuses = $this->extractPackageStatuses($track, $waybills);
        $blocked = [];
        foreach ($statuses as $waybill => $statusLabel) {
            if (!$this->isReadyToShipCancellable($statusLabel)) {
                $blocked[] = [
                    'waybill' => $waybill,
                    'status'  => $statusLabel ?: 'unknown',
                ];
            }
        }

        if ($blocked !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment can only be cancelled while packages are ready to ship (not yet picked up).',
                'data'    => [
                    'blocked' => $blocked,
                    'statuses' => $statuses,
                ],
            ], 422);
        }

        $results = [];
        $failures = [];
        foreach ($waybills as $waybill) {
            $result = $delhiveryService->cancelWaybill($waybill);
            $results[] = [
                'waybill' => $waybill,
                'success' => (bool) ($result['success'] ?? false),
                'error'   => $result['error'] ?? null,
                'raw'     => $result['raw'] ?? ($result['data'] ?? null),
            ];
            if (empty($result['success'])) {
                $failures[] = $waybill . ': ' . ($result['error'] ?? 'failed');
            }
        }

        if ($failures !== []) {
            $shipment->error_message = 'Cancel partially failed: ' . implode('; ', $failures);
            $shipment->response_payload = [
                'cancel_attempt' => $results,
                'track' => $track,
            ];
            $shipment->save();

            return response()->json([
                'success' => false,
                'message' => 'Delhivery could not cancel all packages. ' . implode(' ', $failures),
                'data'    => [
                    'results' => $results,
                ],
            ], 400);
        }

        $shipment->status = 'cancelled';
        $shipment->cancelled_at = Carbon::now();
        $shipment->error_message = null;
        $shipment->response_payload = [
            'cancel' => $results,
            'track_before_cancel' => $track,
        ];
        $shipment->save();

        return response()->json([
            'success' => true,
            'message' => count($waybills) > 1
                ? 'Shipments cancelled with Delhivery. You can book again if needed.'
                : 'Shipment cancelled with Delhivery. You can book again if needed.',
            'data' => [
                'order_id' => $orderId,
                'status'   => $shipment->status,
                'awb_no'   => $shipment->awb_no,
                'results'  => $results,
            ],
        ]);
    }

    /**
     * Fill package total_amount / cod_amount from order line items when possible.
     */
    private function enrichPackagesWithLineAmounts(
        int $orderId,
        array $packages,
        string $paymentMode
    ): array {
        if ($packages === []) {
            return $packages;
        }

        $items = OrderItemModel::where('order_id', $orderId)->orderBy('id')->get();
        $byId = $items->keyBy('id');
        $isCod = strcasecmp($paymentMode, 'COD') === 0;

        foreach ($packages as $i => $package) {
            if (!is_array($package)) {
                continue;
            }
            $item = null;
            $itemId = isset($package['order_item_id']) ? (int) $package['order_item_id'] : 0;
            if ($itemId > 0 && $byId->has($itemId)) {
                $item = $byId->get($itemId);
            } elseif ($items->has($i)) {
                // Fall back to package index → order item order.
                $item = $items[$i];
                $packages[$i]['order_item_id'] = (int) $item->id;
            }

            if (!$item) {
                continue;
            }

            $qty = max(1, (int) $item->quantity);
            $lineTotal = round(((float) $item->price) * $qty, 2);
            if ($lineTotal < 1 && isset($item->subtotal)) {
                $lineTotal = round((float) $item->subtotal, 2);
            }
            if ($lineTotal < 1) {
                continue;
            }

            $packages[$i]['total_amount'] = $lineTotal;
            $packages[$i]['cod_amount'] = $isCod ? $lineTotal : 0.0;
        }

        return $packages;
    }

    /**
     * Delhivery auto-assigns AWBs keyed by client order id. After cancel, the same
     * id (e.g. 29-P1) returns the cancelled waybill — bump to 29-R2, 29-R3, …
     */
    private function nextDelhiveryClientOrderNo(int $orderId, ?OrderShipment $shipment = null): string
    {
        if (!$shipment) {
            return (string) $orderId;
        }

        $status = strtolower((string) $shipment->status);
        $needsNewId = $shipment->cancelled_at
            || !empty($shipment->awb_no)
            || in_array($status, ['cancelled', 'canceled', 'booked', 'failed'], true);

        if (!$needsNewId) {
            return (string) $orderId;
        }

        $prevNo = trim((string) data_get($shipment->request_payload, 'order_no', ''));
        $attempt = 2;
        if ($prevNo !== '' && preg_match(
            '/^' . preg_quote((string) $orderId, '/') . '-R(\d+)$/',
            $prevNo,
            $matches
        )) {
            $attempt = ((int) $matches[1]) + 1;
        }

        return $orderId . '-R' . $attempt;
    }

    private function splitWaybills(string $awbNo): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            preg_split('/[,;\s]+/', $awbNo) ?: []
        ))));
    }

    /**
     * Map waybill => latest Delhivery status label from a track response.
     */
    private function extractPackageStatuses(array $track, array $waybills): array
    {
        $statuses = [];
        foreach ($waybills as $waybill) {
            $statuses[$waybill] = '';
        }

        foreach ((array) data_get($track, 'ShipmentData', []) as $entry) {
            $shipment = is_array($entry) ? ($entry['Shipment'] ?? $entry) : null;
            if (!is_array($shipment)) {
                continue;
            }
            $awb = trim((string) ($shipment['AWB'] ?? $shipment['Awb'] ?? ''));
            if ($awb === '') {
                continue;
            }
            $status = data_get($shipment, 'Status.Status')
                ?? data_get($shipment, 'Status.StatusType')
                ?? data_get($shipment, 'Status.StatusCode')
                ?? data_get($shipment, 'Status')
                ?? '';
            if (is_array($status)) {
                $status = $status['Status'] ?? $status['StatusType'] ?? reset($status) ?: '';
            }
            $statuses[$awb] = trim((string) $status);
        }

        return $statuses;
    }

    /**
     * Only allow cancel before the package is lined up / picked for movement.
     */
    private function isReadyToShipCancellable(?string $status): bool
    {
        $normalized = strtolower(trim((string) $status));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        if ($normalized === '') {
            // Fresh bookings sometimes track empty briefly; treat as cancellable.
            return true;
        }

        $blockedFragments = [
            'in transit',
            'dispatched',
            'out for delivery',
            'ofd',
            'delivered',
            'rto',
            'dto',
            'picked up',
            'ready for pickup',
            'collected',
            'left origin',
            'reached',
            'at destination',
        ];
        foreach ($blockedFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return false;
            }
        }
        // "picked" alone (but allow "not picked")
        if (str_contains($normalized, 'picked') && !str_contains($normalized, 'not picked')) {
            return false;
        }

        $allowed = [
            'manifested',
            'pending',
            'open',
            'scheduled',
            'not picked',
            'ready to ship',
            'udp',
            'uploaded',
        ];
        foreach ($allowed as $label) {
            if ($normalized === $label || str_contains($normalized, $label)) {
                return true;
            }
        }

        // Unknown early statuses: do not cancel blindly.
        return false;
    }
    /**
     * Prefer the package remark (for example an insufficient wallet balance)
     * over Delhivery's generic rmk line.
     */
    private function extractDelhiveryWaybills(array $apiResponse): array
    {
        $waybills = [];
        foreach ((array) data_get($apiResponse, 'packages', []) as $package) {
            if (!is_array($package)) {
                continue;
            }
            $waybill = trim((string) ($package['waybill'] ?? ''));
            if ($waybill !== '') {
                $waybills[] = $waybill;
            }
        }

        return array_values(array_unique($waybills));
    }

    private function delhiveryBookingFailure(?array $apiResponse): ?string
    {
        if (!is_array($apiResponse)) {
            return 'Delhivery did not book the shipment.';
        }

        if ($this->extractDelhiveryWaybills($apiResponse) !== []) {
            return null;
        }

        $packages = (array) data_get($apiResponse, 'packages', []);
        $anyFail = false;
        $lines = [];
        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $packageStatus = strtolower((string) ($package['status'] ?? ''));
            if ($packageStatus === 'fail') {
                $anyFail = true;
            }
            $remarks = $package['remarks'] ?? $package['remark'] ?? null;
            if (is_string($remarks)) {
                $remarks = [$remarks];
            }
            foreach (is_array($remarks) ? $remarks : [] as $line) {
                $line = trim((string) $line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        $failed = ($apiResponse['success'] ?? null) === false
            || $anyFail
            || isset($apiResponse['error'])
            || isset($apiResponse['rmk'])
            || $packages === [];
        if (!$failed) {
            return null;
        }

        if ($lines !== []) {
            return implode(' ', array_unique($lines));
        }

        $rmk = trim((string) ($apiResponse['rmk'] ?? ''));
        if ($rmk !== '') {
            return $rmk;
        }
        if (is_string($apiResponse['error'] ?? null) && trim($apiResponse['error']) !== '') {
            return trim($apiResponse['error']);
        }

        return 'Delhivery did not book the shipment.';
    }

    // Build the shipment data for both checking and punching
    private function buildShipmentData(int $orderId, array $overrides = []): array
    {
        $order = OrderModel::with('user')->findOrFail($orderId);
        $user = $order->user;
        if (!$user) throw new \Exception("User not found for this order.");

        // Items
        $items = OrderItemModel::with([
            'product:id,name',
            'variant:id,weight,length,width,height,variant_value',
        ])
            ->where('order_id', $order->id)
            ->get();

        if ($items->isEmpty()) throw new \Exception("No items found for this order.");

        $descParts = [];
        $totalQty = 0;
        $totalWeight = 0.0;
        $packages = [];
        $paymentModePreview = $order->payment_status === 'paid' ? 'Prepaid' : 'COD';

        foreach ($items as $item) {
            $name = optional($item->product)->name ?? ('Product #'.$item->product_id);
            $variantValue = optional($item->variant)->variant_value;
            $qty  = max(1, (int) $item->quantity);
            $label = $variantValue ? ($name.' ('.$variantValue.')') : $name;

            $descParts[] = $label.' x '.$qty;
            $totalQty += $qty;

            $unitWeight = optional($item->variant)->weight;
            $packageWeight = !is_null($unitWeight)
                ? max(0.01, ((float) $unitWeight) * $qty)
                : 1.0;
            $totalWeight += $packageWeight;

            $length = optional($item->variant)->length;
            $width  = optional($item->variant)->width;
            $height = optional($item->variant)->height;

            $lineTotal = round(((float) $item->price) * $qty, 2);
            $packages[] = [
                'order_item_id'        => (int) $item->id,
                'products_description' => $label.' x '.$qty,
                'quantity'             => $qty,
                'weight'               => round($packageWeight, 3),
                'shipment_length'      => $length !== null ? (float) $length : 10.0,
                'shipment_width'       => $width !== null ? (float) $width : 10.0,
                'shipment_height'      => $height !== null ? (float) $height : 10.0,
                'total_amount'         => $lineTotal,
                'cod_amount'           => $paymentModePreview === 'COD' ? $lineTotal : 0.0,
            ];
        }

        if ($totalWeight <= 0) $totalWeight = 1.0;
        $productsDescription = implode(', ', $descParts);
        $firstPackage = $packages[0] ?? null;

        // Pickup comes from Delhivery client warehouses (aligned below).
        $pickupName = (string) ($overrides['pickup_name'] ?? config('shipping.pickup.name', ''));
        $pickupAddress = (string) config('shipping.pickup.address', '');
        $pickupPin = (string) ($overrides['pickup_pin'] ?? config('shipping.pickup.pin', ''));
        $pickupCity = (string) config('shipping.pickup.city', '');
        $pickupState = (string) config('shipping.pickup.state', '');
        $pickupPhone = (string) config('shipping.pickup.phone', '');

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

        $seller = SettingController::sellerDetails();
        $sellerName = $seller['name'] !== '' ? $seller['name'] : 'Haneri Electricals LLP';
        $sellerAddress = $seller['address'] !== '' ? $seller['address'] : $pickupAddress;
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
            'pickup_pin'           => $pickupPin,
            'pickup_city'          => $pickupCity,
            'pickup_state'         => $pickupState,
            'pickup_phone'         => $pickupPhone,

            'shipment_length'      => $firstPackage['shipment_length'] ?? 10,
            'shipment_width'       => $firstPackage['shipment_width'] ?? 10,
            'shipment_height'      => $firstPackage['shipment_height'] ?? 10,
            'packages'             => $packages,

            'shipping_mode'        => 'Surface',
            'service_level'        => 'normal',
            'address_type'         => 'home',

            'return_pin'           => $pickupPin,
            'return_city'          => $pickupCity,
            'return_phone'         => $pickupPhone,
            'return_address'       => $pickupAddress,
            'return_state'         => $pickupState,
            'return_country'       => 'India',
        ];

        // ✅ Apply overrides (allow only safe keys)
        $allowed = [
            'customer_name','customer_address','phone',
            'shipping_mode','service_level','address_type',
            'shipment_length','shipment_width','shipment_height',
            'pickup_name','pickup_pin','pickup_city','pickup_state',
            'pickup_address','pickup_phone',
            'packages',
        ];

        foreach ($allowed as $k) {
            if (array_key_exists($k, $overrides) && $overrides[$k] !== null && $overrides[$k] !== '') {
                $orderData[$k] = $overrides[$k];
            }
        }

        try {
            $orderData = $this->alignPickupWithDelhivery($orderData);
        } catch (\RuntimeException $e) {
            throw new \Exception($e->getMessage());
        }

        // Return full preview pack
        return [
            'order'      => $order,
            'user'       => $user,
            'pickup'     => [
                'name'    => $orderData['pickup_name'] ?? null,
                'address' => $orderData['pickup_address'] ?? null,
                'pin'     => $orderData['pickup_pin'] ?? null,
                'city'    => $orderData['pickup_city'] ?? null,
                'state'   => $orderData['pickup_state'] ?? null,
                'phone'   => $orderData['pickup_phone'] ?? null,
            ],
            'items'      => $items,
            'packages'   => $orderData['packages'] ?? $packages,
            'orderData'  => $orderData,
        ];
    }

    public function listWarehouses()
    {
        $service = new DelhiveryService();
        $warehouses = DelhiveryService::normalizeClientWarehouses(
            $service->listClientWarehouses()
        );

        return response()->json([
            'success' => $warehouses !== [],
            'message' => $warehouses === []
                ? ($service->lastWarehouseError() ?: 'No Delhivery warehouses found.')
                : 'Warehouses fetched.',
            'data' => $warehouses,
        ]);
    }

    /**
     * Delhivery only accepts a pickup name that exists as a client warehouse.
     * A local label such as "Kolkata" is replaced with the registered warehouse.
     */
    private function alignPickupWithDelhivery(array $orderData): array
    {
        $service = new DelhiveryService();
        $resolved = DelhiveryService::matchClientWarehouse(
            $service->listClientWarehouses(),
            (string) ($orderData['pickup_name'] ?? ''),
            isset($orderData['pickup_pin']) ? (string) $orderData['pickup_pin'] : null
        );

        $match = $resolved['match'];
        if (!$match) {
            if ($resolved['names'] !== []) {
                throw new \RuntimeException(
                    'Pickup "'.($orderData['pickup_name'] ?? '').'" is not registered with Delhivery. Use one of: '.implode(', ', $resolved['names']).'.'
                );
            }
            $err = trim($service->lastWarehouseError());
            throw new \RuntimeException(
                $err !== ''
                    ? $err
                    : 'No Delhivery warehouses found. Add a pickup location in Delhivery One.'
            );
        }

        $orderData['pickup_name'] = $match['name'];
        if ($match['pin'] !== '') {
            $orderData['pickup_pin'] = $match['pin'];
            $orderData['return_pin'] = $match['return_pin'] !== '' ? $match['return_pin'] : $match['pin'];
        }
        if ($match['address'] !== '') {
            $orderData['pickup_address'] = $match['address'];
            $orderData['return_address'] = $match['return_address'] !== '' ? $match['return_address'] : $match['address'];
        }
        if ($match['city'] !== '') {
            $orderData['pickup_city'] = $match['city'];
            $orderData['return_city'] = $match['return_city'] !== '' ? $match['return_city'] : $match['city'];
        }
        if ($match['state'] !== '') {
            $orderData['pickup_state'] = $match['state'];
            $orderData['return_state'] = $match['return_state'] !== '' ? $match['return_state'] : $match['state'];
        }
        if ($match['phone'] !== '') {
            $orderData['pickup_phone'] = $match['phone'];
            $orderData['return_phone'] = $match['return_phone'] !== '' ? $match['return_phone'] : $match['phone'];
        }

        return $orderData;
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
    
}