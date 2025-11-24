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
        // 1. Basic validation for "through"
        $validator = Validator::make($request->all(), [
            'through'   => 'required|in:order,simple',

            // when through = order
            'order_id'  => 'required_if:through,order|integer|exists:t_orders,id',

            // when through = simple
            'origin_pin'      => 'required_if:through,simple|digits:6',
            'destination_pin' => 'required_if:through,simple|digits:6',
            'cod_amount'      => 'required_if:through,simple|numeric',
            'weight'          => 'required_if:through,simple|numeric',
            'payment_type'    => 'nullable|in:Pre-paid,COD',
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
        // CASE 1: through = "order"
        // =========================
        if ($through === 'order') {
            $orderId = $request->input('order_id');

            // Get latest shipment for this order
            $shipment = OrderShipment::where('order_id', $orderId)
                ->orderByDesc('id')
                ->first();

            if (!$shipment) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'No shipment record found for this order.',
                    'data'    => [],
                ], 404);
            }

            // You can also fetch order if needed
            $order = OrderModel::find($orderId);

            // ---- Map DB fields to Delhivery params ----
            // Adjust these according to your real DB data
            $originPin      = $shipment->pickup_pin;     // pickup pincode
            $destinationPin = $shipment->shipping_pin;   // customer pincode
            $weight         = $shipment->weight ?? 1;    // default to 1kg if null

            // Payment / COD logic
            // Assuming payment_mode in ['Pre-paid','COD'] or similar
            $paymentMode = $shipment->payment_mode ?? 'Pre-paid';

            $paymentType = $paymentMode === 'COD' ? 'COD' : 'Pre-paid';

            // If you store cod_amount separately, use that, else fall back to total_amount
            $codAmount = $paymentType === 'COD'
                ? ($shipment->cod_amount ?? $shipment->total_amount ?? 0)
                : 0;

        } else {
        // =========================
        // CASE 2: through = "simple"
        // =========================
            $originPin      = $request->input('origin_pin');
            $destinationPin = $request->input('destination_pin');
            $codAmount      = $request->input('cod_amount');
            $weight         = $request->input('weight');
            $paymentType    = $request->input('payment_type', 'Pre-paid');
        }

        // =========================
        // Call Delhivery Service
        // =========================
        $delhiveryService = new DelhiveryService();

        $response = $delhiveryService->getShippingCost(
            $originPin,
            $destinationPin,
            $codAmount,
            $weight,
            $paymentType
        );

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

            $productsDescription = implode(', ', $descParts);

            // 5) Map shipping details from single shipping_address string
            $shippingAddress = $order->shipping_address;

            // Default nulls
            $pin     = null;
            $city    = null;
            $state   = null;
            $country = null;
            $nameFromAddress  = null;
            $phoneFromAddress = null;

            if ($shippingAddress) {
                // Split by comma and trim
                $parts = array_map('trim', explode(',', $shippingAddress));
                // Remove empty values and reindex
                $parts = array_values(array_filter($parts, fn($v) => $v !== ''));

                $count = count($parts);

                if ($count >= 7) {
                    // According to your pattern:
                    // 0 -> name
                    // 1 -> mobile
                    // ... middle address chunks ...
                    // [count-5] -> city
                    // [count-4] -> district (not used for Delhivery)
                    // [count-3] -> state
                    // [count-2] -> pin
                    // [count-1] -> country

                    $nameFromAddress  = $parts[0] ?? null;
                    $phoneFromAddress = $parts[1] ?? null;

                    $country = $parts[$count - 1] ?? null;
                    $maybePin = $parts[$count - 2] ?? null;
                    $state  = $parts[$count - 3] ?? null;
                    // district = $parts[$count - 4] ?? null; // if you ever need it
                    $city   = $parts[$count - 5] ?? null;

                    // Validate pin shape (6 digits)
                    if ($maybePin && preg_match('/^\d{6}$/', $maybePin)) {
                        $pin = $maybePin;
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

            // Optionally, you can also override phone from address if missing:
            $finalPhone = $user->mobile ?? $phoneFromAddress ?? null;

            // 6) Payment mode from order.payment_status
            // tweak mapping as per your logic
            $paymentMode = $order->payment_status === 'paid' ? 'Prepaid' : 'COD';
            $codAmount   = $paymentMode === 'COD' ? (float) $order->total_amount : 0.0;

            // Seller can still come from config
            $sellerName    = config('shipping.seller_name', 'Your Store Name');
            $sellerAddress = config('shipping.seller_address', 'Your Warehouse Address');
            $sellerInvoice = 'INV-' . $order->id; // or $order->invoice_no etc.

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

            // 8) Build payload for DelhiveryService->placeOrder()
            $orderData = [
                // Customer
                'customer_name'        => $user->name,
                'customer_address'     => $shippingAddress,
                'pin'                  => $pin,
                'city'                 => $city,
                'state'                => $state,
                'phone'                => $finalPhone, //$user->mobile,

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
            $shipment->user_id              = $order->user_id;
            $shipment->courier              = 'delhivery';
            $shipment->pickup_location_id   = $pickup->id;   // ⭐ store pickup id here
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
        $orderId     = $request->query('order_id');   // new
        $waybillRaw  = $request->query('waybill');    // optional, keep for direct tracking

        // You must provide either order_id OR waybill
        if (!$orderId && !$waybillRaw) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Either order_id or waybill parameter is required.',
                'data'    => [],
            ], 422);
        }

        $waybillList = [];

        // -----------------------------
        // CASE 1: Track by order_id
        // -----------------------------
        if ($orderId) {
            // Fetch all shipments for this order that have an AWB
            $awbNumbers = OrderShipment::where('order_id', $orderId)
                ->whereNotNull('awb_no')
                ->pluck('awb_no')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($awbNumbers)) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'No AWB found for this order.',
                    'data'    => [],
                ], 404);
            }

            $waybillList = $awbNumbers;
        }

        // -----------------------------
        // CASE 2: Track by waybill param
        // -----------------------------
        if (!$orderId && $waybillRaw) {
            $waybillList = array_filter(array_map('trim', explode(',', $waybillRaw)));
            if (empty($waybillList)) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'The waybill parameter is empty or invalid.',
                    'data'    => [],
                ], 422);
            }
        }

        // Manual service (no DI)
        $delhiveryService = new DelhiveryService();

        try {
            $response = $delhiveryService->trackShipments($waybillList);
        } catch (\Exception $e) {
            \Log::error('Delhivery trackShipments exception: ' . $e->getMessage());

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

    // public function trackShipments(Request $request)
    // {
    //     $waybill = $request->query('waybill');
    //     $refIds  = $request->query('ref_ids'); // optional

    //     if (!$waybill) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'The waybill parameter is required.',
    //             'data'    => [],
    //         ], 422);
    //     }

    //     // Multiple comma-separated waybills supported by Delhivery
    //     $waybillList = array_map('trim', explode(',', $waybill));

    //     // Manual service (no DI)
    //     $delhiveryService = new DelhiveryService();

    //     $response = $delhiveryService->trackShipments($waybillList);

    //     if (isset($response['error'])) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $response['error'],
    //             'data'    => [],
    //         ], 400);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Shipment tracking fetched.',
    //         'data'    => $response,
    //     ]);
    // }

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