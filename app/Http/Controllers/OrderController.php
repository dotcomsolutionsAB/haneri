<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\OrderShipment;
use App\Models\CartModel;
use App\Models\User;
use Carbon\Carbon;
use DB;
use App\Http\Controllers\RazorpayController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdate;
use App\Models\PaymentModel;
use App\Models\CouponModel;


class OrderController extends Controller
{
    //
    public function store(Request $request)
    {
        // Validate request data
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled,refunded',
            'payment_status' => 'required|in:pending,paid,failed',
            // 'payment_status' => 'pending',
            'shipping_address' => 'required|string',
            'shipping_charge' => 'nullable|numeric|min:0',
            'payment_mode'    => 'nullable|in:Prepaid,COD', // optional, but helpful
            'coupon_code' => 'nullable|string|max:100',
        ]);

        $user = Auth::user(); 

        if ($user->role == 'admin') {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
            ]);  
            $user_id =  $request->input('user_id');
        }

        else {
            $user_id =  $user->id;
        }

        // Fetch user details from User model
        $orderUser = User::find($user_id);
        if (!$orderUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user_name = $orderUser->name;  // Fetch name
        $user_email = $orderUser->email;  // Fetch email
        $user_phone = $orderUser->mobile;  // Fetch mobile (Ensure the column exists in the `users` table)

        // Start a transaction to ensure all operations are atomic
        DB::beginTransaction();

        try{
            // Fetch all items from the cart for the user
            // $cartItems = CartModel::where('user_id', $user_id)->get();
            $cartItems = CartModel::where('user_id', (string)$user_id)->get();


            // Check if the cart is empty
            if ($cartItems->isEmpty()) {
                DB::rollBack(); // 🔴 this was missing
                return response()->json(['message' => 'Sorry, cart is empty.'], 400);
            }

            // Calculate the total amount by iterating through the cart items
            $totalAmount = 0.0;

            foreach ($cartItems as $cartItem) {
                $linePrice = $this->getFinalPrice($orderUser, $cartItem->product_id, $cartItem->variant_id);
                $totalAmount += $linePrice * (int)$cartItem->quantity;
            }
            
            // ✅ COUPON: validate + apply discount on subtotal (products total)
            $couponCode = trim((string) $request->input('coupon_code', ''));
            $discountAmount = 0.0;

            if ($couponCode !== '') {

                $coupon = CouponModel::query()
                    ->where('coupon_code', $couponCode)
                    ->where('status', 'active')
                    ->whereDate('validity', '>=', now()->toDateString())
                    ->where(function ($qq) use ($user_id) {
                        $qq->whereNull('user_id')
                        ->orWhere('user_id', $user_id);
                    })
                    ->first();

                if (!$coupon) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Invalid coupon or coupon expired / inactive.',
                    ], 422);
                }

                // If you treat "count" as remaining usable count, block when <= 0
                if ((int)$coupon->count <= 0) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Coupon usage limit exceeded.',
                    ], 422);
                }

                // Calculate discount amount
                if ($coupon->discount_type === 'percentage') {
                    $discountAmount = round($totalAmount * ((float)$coupon->discount_value / 100), 2);
                } else { // price
                    $discountAmount = (float) $coupon->discount_value;
                }

                // Don't allow discount more than subtotal
                $discountAmount = min($discountAmount, $totalAmount);

                // Apply discount on subtotal only (not on shipping)
                $totalAmount = max(0, $totalAmount - $discountAmount);
            }

            // shipping charge from request (default 0)
            $shippingCharge = (float) $request->input('shipping_charge', 0);
            // Final payable amount in rupees
            $finalAmount = (float) $totalAmount + (float) $shippingCharge;

            // Convert to paise for Razorpay (must be integer)
            // $amountInPaise = (int) round($finalAmount * 100);
            $amountInPaise = (int) ($finalAmount * 100);

            // Call Razorpay Order API Before Saving Order in DB
            $razorpayController = new RazorpayController(); 
            $razorpayRequest = new Request([
                'amount'   => $amountInPaise, // paise
                'currency' => 'INR'
            ]);
            $razorpayResponse = $razorpayController->createOrder($razorpayRequest);


            // Decode Razorpay response
            $razorpayData = json_decode($razorpayResponse->getContent(), true);

            if (!($razorpayData['success'] ?? false) || empty($razorpayData['order']['id'])) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to create Razorpay order.'], 500);
            }

            // Create the order record
            $order = OrderModel::create([
                'user_id' => $user_id,
                'total_amount' => $finalAmount, // include shipping
                'shipping_charge' => $shippingCharge, // if you have this column (recommended)
                'status' => $request->input('status', 'pending'),
                'payment_status' => $request->input('payment_status', 'pending'),
                'shipping_address' => $request->input('shipping_address'),
                'razorpay_order_id' => $razorpayData['order']['id'],
            ]);

            $ship = $this->parseShippingAddress((string) $order->shipping_address);

            // these three you asked for:
            $shippingPin   = $ship['pin'] ?? null;
            $shippingCity  = $ship['city'] ?? null;
            $shippingState = $ship['state'] ?? null;

            // (optional) if you want to use extracted customer name/phone instead of profile values:
            $shipCustomerName  = $ship['name']  ?: $user_name;
            $shipCustomerPhone = $ship['phone'] ?: $user_phone;

            // --- AUTO SHIP SETUP (runs when order is punched) ---
            OrderShipment::create([
                'order_id'        => $order->id,
                'user_id'         => $orderUser->id,
                'courier'         => 'delhivery',
                'status'          => 'setup',

                'customer_name'   => $shipCustomerName,   // optional override from shipping_address
                'customer_phone'  => $shipCustomerPhone,  // optional override from shipping_address
                'customer_email'  => $user_email,
                'shipping_address'=> $order->shipping_address,

                // if you have separate columns for pin/city/state then map them here:
                'shipping_pin'    => $shippingPin,
                'shipping_city'   => $shippingCity,
                'shipping_state'  => $shippingState,

                // Amounts
                'payment_mode'    => $request->input('payment_mode', 'Prepaid'), // if you store it somewhere
                'total_amount'    => $order->total_amount,
                'cod_amount'      => $request->input('payment_mode') === 'COD'
                                    ? $order->total_amount
                                    : 0,

                // Package summary (simple default)
                'quantity'        => $cartItems->sum('quantity'),
                'weight'          => null, // we can set in manual API
                'products_description' => 'Order #'.$order->id.' items',

                // Pickup info – if you have configured somewhere, you can fill from config
                'pickup_location_id' => config('shipping.pickup.location_id', 1),
                'pickup_name'     => config('shipping.pickup.name', 'Default Pickup'),
                'pickup_address'  => config('shipping.pickup.address', ''),
                'pickup_pin'      => config('shipping.pickup.pin', ''),
                'pickup_city'     => config('shipping.pickup.city', ''),
                'pickup_state'    => config('shipping.pickup.state', ''),
                'pickup_phone'    => config('shipping.pickup.phone', ''),
            ]);

            // Iterate through each cart item to add it to the order items table
            foreach ($cartItems as $cartItem) {
                $linePrice = $this->getFinalPrice($orderUser, $cartItem->product_id, $cartItem->variant_id);

                OrderItemModel::create([
                    'order_id'   => $order->id,
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'quantity'   => $cartItem->quantity,
                    'price'      => $linePrice, // lock the computed selling price
                ]);
            }

            // After successfully adding order items, delete the cart items
            CartModel::where('user_id', (string)$user_id)->delete();
            /**
             * 🔹 Create initial payment record (in t_payment_records)
             * - status = same as order payment_status (usually "pending" here)
             * - method = Razorpay (or COD / Prepaid based on your logic)
             * - razorpay_payment_id will be filled later from webhook / callback
             */
            PaymentModel::create([
                'method'             => $request->input('payment_mode', 'razorpay'),
                'razorpay_payment_id'=> null,  // will be updated after successful payment
                'amount'             => $finalAmount,
                'status'             => $request->input('payment_status', 'pending'),
                'order_id'           => $order->id,
                'razorpay_order_id'  => $order->razorpay_order_id,
                'user'               => $user_id, // assuming this column stores user_id
            ]);

            // Commit the transaction
            DB::commit();

            // Build line items for the email (with product/variant names)
            $items = OrderItemModel::with(['product:id,name', 'variant:id,variant_type,variant_value'])
                ->where('order_id', $order->id)
                ->get()
                ->map(function($it) {
                    // Build a human label like "Size: Large" (fallbacks handled)
                    $vType  = optional($it->variant)->variant_type;
                    $vValue = optional($it->variant)->variant_value;
                    $variantLabel = $vValue
                        ? ($vType ? ($vType . ': ' . $vValue) : $vValue)
                        : null;

                    return [
                        'name'    => optional($it->product)->name ?? ('Product #'.$it->product_id),
                        'variant' => $variantLabel,
                        'qty'     => (int) $it->quantity,
                        'price'   => (float) $it->price,
                        'total'   => (float) $it->price * (int)$it->quantity,
                    ];
                })
                ->toArray();

            // Prepare response
            $response = [
                'message' => 'Order created successfully!',
                'data' => [
                    'order_id' => $order->id,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'shipping_address' => $order->shipping_address,
                    'razorpay_order_id' => $order->razorpay_order_id,
                    'name' => $user_name,
                    'email' => $user_email, 
                    'phone' => $user_phone, 
                ]
            ];

            // Return success response
            return response()->json(['message' => 'Order created successfully!', 'data' => $response], 201);
        }

        catch(\Exception $e)
        {
            // Log the exception for debugging
            \Log::error('Failed to create order: ' . $e->getMessage());

            // In case of any failure, roll back the transaction
            DB::rollBack();

            // Return error response
            return response()->json(['message' => 'Failed to create order. Please try again.', 'error' => $e->getMessage()], 500);
        }
    }
    // Address parse
    private function parseShippingAddress(string $raw): array
    {
        // Example raw:
        // "Dhruv Taneja, 9310449009, Faridabad, Haryana, India, 121007, 1157, Sector 15"

        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));

        $name    = $parts[0] ?? null;
        $phone   = $parts[1] ?? null;
        $city    = $parts[2] ?? null;
        $state   = $parts[3] ?? null;
        $country = $parts[4] ?? null;
        $pin     = $parts[5] ?? null;

        // Everything after pin is address parts
        $addrParts = array_slice($parts, 6);

        // As per your requirement:
        // address line 1 = "1157, Sector 15" (take next 2 parts if available)
        // if more than that => address line 2
        $line1 = null;
        $line2 = null;

        if (count($addrParts) >= 2) {
            $line1 = trim($addrParts[0] . ', ' . $addrParts[1]);
            $line2 = count($addrParts) > 2 ? trim(implode(', ', array_slice($addrParts, 2))) : null;
        } elseif (count($addrParts) === 1) {
            $line1 = $addrParts[0];
        }

        return [
            'name'     => $name,
            'phone'    => $phone,
            'city'     => $city,
            'state'    => $state,
            'country'  => $country,
            'pin'      => $pin,
            'line1'    => $line1,
            'line2'    => $line2,
            'parts'    => $parts,
        ];
    }
    // Add this helper if you don’t already have it in this controller
    private function price(float $regular, float $discountPercent = 0): float
    {
        $discountPercent = max(0, min(100, (float)$discountPercent));
        $selling = $regular * (1 - ($discountPercent / 100));
        return round($selling, 2);
    }
    // Replace your getFinalPrice with this version (note it needs $user)
    private function getFinalPrice(User $user, int $product_id, ?int $variant_id = null): float
    {
        $product = \App\Models\ProductModel::find($product_id);
        if (!$product) {
            return 0.0;
        }

        // If no variant, you can decide how to price (product price or 0). Here we keep product price.
        if (!$variant_id) {
            return (float)($product->price ?? 0);
        }

        $variant = \App\Models\ProductVariantModel::find($variant_id);
        if (!$variant) {
            return 0.0;
        }

        // 1) User-specific discount (per user + variant)
        $userDiscount = \App\Models\UsersDiscountModel::where('user_id', $user->id)
            ->where('product_variant_id', $variant->id)
            ->value('discount'); // percentage or null

        // 2) Fallback to role-based discount on the variant
        if ($userDiscount === null) {
            switch ($user->role) {
                case 'customer':
                    $userDiscount = $variant->customer_discount;
                    break;
                case 'dealer':
                    $userDiscount = $variant->dealer_discount;
                    break;
                case 'architect':
                    $userDiscount = $variant->architect_discount;
                    break;
                default:
                    $userDiscount = 0;
                    break;
            }
        }

        // Base is variant->regular_price (mirrors your cart logic)
        $regular = (float) ($variant->regular_price ?? 0);
        return $this->price($regular, (float)$userDiscount);
    }

    // public function store(Request $request)
    // {
    //     // Validate request data
    //     $request->validate([
    //         'status' => 'required|in:pending,completed,cancelled,refunded',
    //         // 'payment_status' => 'required|in:pending,paid,failed',
    //         'payment_status' => 'pending',
    //         'shipping_address' => 'required|string',
    //         'shipping_charge' => 'nullable|numeric|min:0',
    //         'payment_mode'    => 'nullable|in:Prepaid,COD', // optional, but helpful
    //     ]);

    //     $user = Auth::user(); 

    //     if ($user->role == 'admin') {
    //         $request->validate([
    //             'user_id' => 'required|integer|exists:users,id',
    //         ]);  
    //         $user_id =  $request->input('user_id');
    //     }

    //     else {
    //         $user_id =  $user->id;
    //     }

    //     // Fetch user details from User model
    //     $orderUser = User::find($user_id);
    //     if (!$orderUser) {
    //         return response()->json(['message' => 'User not found.'], 404);
    //     }

    //     $user_name = $orderUser->name;  // Fetch name
    //     $user_email = $orderUser->email;  // Fetch email
    //     $user_phone = $orderUser->mobile;  // Fetch mobile (Ensure the column exists in the `users` table)

    //     // Start a transaction to ensure all operations are atomic
    //     DB::beginTransaction();

    //     try{
    //         // Fetch all items from the cart for the user
    //         // $cartItems = CartModel::where('user_id', $user_id)->get();
    //         $cartItems = CartModel::where('user_id', (string)$user_id)->get();


    //         // Check if the cart is empty
    //         if ($cartItems->isEmpty()) {
    //             DB::rollBack(); // 🔴 this was missing
    //             return response()->json(['message' => 'Sorry, cart is empty.'], 400);
    //         }

    //         // Calculate the total amount by iterating through the cart items
    //         $totalAmount = 0.0;

    //         foreach ($cartItems as $cartItem) {
    //             $linePrice = $this->getFinalPrice($orderUser, $cartItem->product_id, $cartItem->variant_id);
    //             $totalAmount += $linePrice * (int)$cartItem->quantity;
    //         }
            
    //         // shipping charge from request (default 0)
    //         $shippingCharge = (float) $request->input('shipping_charge', 0);
    //         // Final payable amount in rupees
    //         $finalAmount = (float) $totalAmount + (float) $shippingCharge;

    //         // Convert to paise for Razorpay (must be integer)
    //         // $amountInPaise = (int) round($finalAmount * 100);
    //         $amountInPaise = (int) ($finalAmount * 100);

    //         // Call Razorpay Order API Before Saving Order in DB
    //         $razorpayController = new RazorpayController(); 
    //         $razorpayRequest = new Request([
    //             'amount'   => $amountInPaise, // paise
    //             'currency' => 'INR'
    //         ]);
    //         $razorpayResponse = $razorpayController->createOrder($razorpayRequest);


    //         // Decode Razorpay response
    //         $razorpayData = json_decode($razorpayResponse->getContent(), true);

    //         if (!($razorpayData['success'] ?? false) || empty($razorpayData['order']['id'])) {
    //             DB::rollBack();
    //             return response()->json(['message' => 'Failed to create Razorpay order.'], 500);
    //         }

    //         // Create the order record
    //         $order = OrderModel::create([
    //             'user_id' => $user_id,
    //             'total_amount' => $finalAmount, // include shipping
    //             'shipping_charge' => $shippingCharge, // if you have this column (recommended)
    //             'status' => $request->input('status', 'pending'),
    //             'payment_status' => $request->input('payment_status', 'pending'),
    //             'shipping_address' => $request->input('shipping_address'),
    //             'razorpay_order_id' => $razorpayData['order']['id'],
    //         ]);

    //         // --- AUTO SHIP SETUP (runs when order is punched) ---
    //         OrderShipment::create([
    //             'order_id'        => $order->id,
    //             'user_id'         => $orderUser->id,
    //             'courier'         => 'delhivery',
    //             'status'          => 'setup',

    //             'customer_name'   => $user_name,
    //             'customer_phone'  => $user_phone,
    //             'customer_email'  => $user_email,
    //             'shipping_address'=> $order->shipping_address,

    //             // if you have separate columns for pin/city/state then map them here:
    //             'shipping_pin'    => $order->shipping_pin ?? null,
    //             'shipping_city'   => $order->shipping_city ?? null,
    //             'shipping_state'  => $order->shipping_state ?? null,

    //             // Amounts
    //             'payment_mode'    => $request->input('payment_mode', 'Prepaid'), // if you store it somewhere
    //             'total_amount'    => $order->total_amount,
    //             'cod_amount'      => $request->input('payment_mode') === 'COD'
    //                                 ? $order->total_amount
    //                                 : 0,

    //             // Package summary (simple default)
    //             'quantity'        => $cartItems->sum('quantity'),
    //             'weight'          => null, // we can set in manual API
    //             'products_description' => 'Order #'.$order->id.' items',

    //             // Pickup info – if you have configured somewhere, you can fill from config
    //             'pickup_location_id' => config('shipping.default_pickup.location_id', 1),
    //             'pickup_name'     => config('shipping.default_pickup.name', 'Default Pickup'),
    //             'pickup_address'  => config('shipping.default_pickup.address', ''),
    //             'pickup_pin'      => config('shipping.default_pickup.pin', ''),
    //             'pickup_city'     => config('shipping.default_pickup.city', ''),
    //             'pickup_state'    => config('shipping.default_pickup.state', ''),
    //             'pickup_phone'    => config('shipping.default_pickup.phone', ''),
    //         ]);

    //         // Iterate through each cart item to add it to the order items table
    //         foreach ($cartItems as $cartItem) {
    //             $linePrice = $this->getFinalPrice($orderUser, $cartItem->product_id, $cartItem->variant_id);

    //             OrderItemModel::create([
    //                 'order_id'   => $order->id,
    //                 'product_id' => $cartItem->product_id,
    //                 'variant_id' => $cartItem->variant_id,
    //                 'quantity'   => $cartItem->quantity,
    //                 'price'      => $linePrice, // lock the computed selling price
    //             ]);
    //         }

    //         // After successfully adding order items, delete the cart items
    //         CartModel::where('user_id', (string)$user_id)->delete();
    //         /**
    //          * 🔹 Create initial payment record (in t_payment_records)
    //          * - status = same as order payment_status (usually "pending" here)
    //          * - method = Razorpay (or COD / Prepaid based on your logic)
    //          * - razorpay_payment_id will be filled later from webhook / callback
    //          */
    //         PaymentModel::create([
    //             'method'             => $request->input('payment_mode', 'razorpay'),
    //             'razorpay_payment_id'=> null,  // will be updated after successful payment
    //             'amount'             => $finalAmount,
    //             'status'             => $request->input('payment_status', 'pending'),
    //             'order_id'           => $order->id,
    //             'razorpay_order_id'  => $order->razorpay_order_id,
    //             'user'               => $user_id, // assuming this column stores user_id
    //         ]);

    //         // Commit the transaction
    //         DB::commit();

    //         // Build line items for the email (with product/variant names)
    //         $items = OrderItemModel::with(['product:id,name', 'variant:id,variant_type,variant_value'])
    //             ->where('order_id', $order->id)
    //             ->get()
    //             ->map(function($it) {
    //                 // Build a human label like "Size: Large" (fallbacks handled)
    //                 $vType  = optional($it->variant)->variant_type;
    //                 $vValue = optional($it->variant)->variant_value;
    //                 $variantLabel = $vValue
    //                     ? ($vType ? ($vType . ': ' . $vValue) : $vValue)
    //                     : null;

    //                 return [
    //                     'name'    => optional($it->product)->name ?? ('Product #'.$it->product_id),
    //                     'variant' => $variantLabel,
    //                     'qty'     => (int) $it->quantity,
    //                     'price'   => (float) $it->price,
    //                     'total'   => (float) $it->price * (int)$it->quantity,
    //                 ];
    //             })
    //             ->toArray();

    //         // Prepare response
    //         $response = [
    //             'message' => 'Order created successfully!',
    //             'data' => [
    //                 'order_id' => $order->id,
    //                 'total_amount' => $order->total_amount,
    //                 'status' => $order->status,
    //                 'payment_status' => $order->payment_status,
    //                 'shipping_address' => $order->shipping_address,
    //                 'razorpay_order_id' => $order->razorpay_order_id,
    //                 'name' => $user_name,
    //                 'email' => $user_email, 
    //                 'phone' => $user_phone, 
    //             ]
    //         ];

    //         // Return success response
    //         return response()->json(['message' => 'Order created successfully!', 'data' => $response], 201);
    //     }

    //     catch(\Exception $e)
    //     {
    //         // Log the exception for debugging
    //         \Log::error('Failed to create order: ' . $e->getMessage());

    //         // In case of any failure, roll back the transaction
    //         DB::rollBack();

    //         // Return error response
    //         return response()->json(['message' => 'Failed to create order. Please try again.', 'error' => $e->getMessage()], 500);
    //     }
    // }

    // View all orders for a user    
    public function index(Request $request)
    {
        $user = Auth::user(); 

        // If the user is an admin, validate user_id in the request
        if ($user->role === 'admin') {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
            ]);
            $user_id = $request->input('user_id');
        } else {
            $user_id = $user->id;
        }

        $orders = OrderModel::with(['items', 'user', 'invoiceFile'])
            ->where('user_id', $user_id)
            ->where('payment_status', 'paid')   // ✅ only paid orders
            ->get()
            ->map(function ($order) {
                // 🔹 1) Build invoice URL (prefer DB row, fallback to pattern)
                $invoiceId  = $order->invoice_id;
                $invoiceUrl = null;

                if ($order->invoiceFile && $order->invoiceFile->file_path) {
                    // from t_uploads.file_path
                    $invoiceUrl = asset('storage/' . $order->invoiceFile->file_path);
                } elseif ($invoiceId) {
                    // fallback: build by convention HAN-INV-{order_id}.pdf
                    $invoiceNumber = 'HAN-INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                    $relativePath  = 'upload/order_invoice/' . $invoiceNumber . '.pdf';
                    $invoiceUrl    = asset('storage/' . $relativePath);
                }

                // 🔹 2) Hide unwanted fields from items
                if ($order->items) {
                    $order->items->makeHidden(['id', 'created_at', 'updated_at']);
                }

                // 🔹 3) Hide unwanted fields from user
                if ($order->user) {
                    $order->user->makeHidden(['id', 'created_at', 'updated_at']);
                }

                // 🔹 4) Hide internal fields from order (but KEEP invoice_id)
                $order->makeHidden([
                    'created_at',
                    'updated_at',
                    'invoiceFile',   // hide relation completely
                ]);

                // 🔹 5) Attach clean invoice data
                $order->invoice = [
                    'id'  => $invoiceId,
                    'url' => $invoiceUrl,
                ];

                return $order;
            });

        return response()->json([
            'message' => $orders->isNotEmpty()
                ? 'Orders fetched successfully!'
                : 'No orders found.',
            'data'  => $orders,
            'count' => $orders->count(),
        ], 200);
    }

    // Update order & payment statuses (user side on punched order)
    public function statusUpdate(Request $request, $orderId)
    {
        $validated = $request->validate([
            'status'          => 'nullable|string|in:pending,confirmed,processing,completed,cancelled',
            'payment_status'  => 'nullable|string|in:pending,paid,failed,refunded',
            'delivery_status' => 'nullable|string|in:pending,shipped,out_for_delivery,delivered,cancelled',
        ]);

        if (
            !array_key_exists('status', $validated) &&
            !array_key_exists('payment_status', $validated) &&
            !array_key_exists('delivery_status', $validated)
        ) {
            return response()->json([
                'code'    => 400,
                'success' => false,
                'message' => 'No status fields provided to update.',
                'data'    => [],
            ], 400);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // Fetch order belonging to logged-in user
            $order = OrderModel::with('payments')
                ->where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Order not found for this user.',
                    'data'    => [],
                ], 404);
            }

            // 🔹 Update order fields
            if (array_key_exists('status', $validated)) {
                $order->status = $validated['status'];
            }

            if (array_key_exists('payment_status', $validated)) {
                $order->payment_status = $validated['payment_status'];
            }

            if (array_key_exists('delivery_status', $validated)) {
                $order->delivery_status = $validated['delivery_status'];
            }

            $order->save();

            // 🔹 Update payment records IF payment_status sent
            if (array_key_exists('payment_status', $validated)) {
                // adjust 'status' to your actual column name if needed
                $order->payments()->update([
                    'status' => $validated['payment_status'],
                ]);
            }

            DB::commit();

            // ⬇️ VERY IMPORTANT: reload payments so response is fresh
            $order->load('payments');

            // Optional: hide unwanted fields
            $order->makeHidden(['created_at', 'updated_at']);
            if ($order->relationLoaded('payments')) {
                $order->payments->each(function ($p) {
                    $p->makeHidden(['created_at', 'updated_at']);
                });
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Order status updated successfully!',
                'data'    => $order,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to update order status.',
                'data'    => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    // View details of a single order
    public function show($id)
    {
        $user = Auth::user();

        // Fetch the specific order for the logged-in user
        $order = OrderModel::with([
                'items.product',   // make sure relation exists on OrderItemModel
                'items.variant',   // make sure relation exists on OrderItemModel
                'user'
            ])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Order not found.',
                'data'    => [],
            ], 404);
        }

        // Transform items list
        $items = $order->items->map(function ($item) {
            return [
                'product_id'    => $item->product_id,
                'product_name'  => optional($item->product)->name,      // change if your column is different
                'variant value' => optional($item->variant)->variant_value,     // if you prefer key "variant_value", rename here
                'variant_id'    => $item->variant_id,
                'quantity'      => $item->quantity,
                'price'         => $item->price,
            ];
        });

        // Transform user (remove gstin and other unwanted stuff)
        $userData = null;
        if ($order->user) {
            $userData = [
                'name'   => $order->user->name,
                'email'  => $order->user->email,
                'mobile' => $order->user->mobile,
                'role'   => $order->user->role,
                // no gstin here 👍
            ];
        }

        // Build final response data (only required fields)
        $data = [
            'invoice_id'        => $order->invoice_id,
            'total_amount'      => $order->total_amount,
            'status'            => $order->status,
            'payment_status'    => $order->payment_status,
            'delivery_status'   => $order->delivery_status,
            'shipping_address'  => $order->shipping_address,
            'razorpay_order_id' => $order->razorpay_order_id,
            'items'             => $items,
            'user'              => $userData,
        ];

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Order details fetched successfully!',
            'data'    => $data,
        ], 200);
    }

    // delete an order
    public function delete($orderId)
    {
        try {
            DB::beginTransaction();

            // Fetch the order with relations (optional but nice)
            $order = OrderModel::with(['items', 'payments', 'shipments'])->find($orderId);

            if (!$order) {
                DB::rollBack(); // rollback before returning
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found!',
                ], 404);
            }

            // Delete related order items
            $order->items()->delete();

            // Delete related payments
            $order->payments()->delete();

            // Delete related shipments
            $order->shipments()->delete();

            // Finally delete the order
            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order, items, payments and shipments deleted successfully!',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // for all orders
    public function fetchOrders(Request $request)
    {
        try {
            // 1) AuthZ: only admins
            $me = Auth::user();
            if (!$me || $me->role !== 'admin') {
                return response()->json([
                    'code'    => 403,
                    'success' => false,
                    'message' => 'Unauthorized',
                    'data'    => [],
                ], 403);
            }

            // 2) Inputs (with sane defaults / guards)
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $limit  = $limit > 0 ? min($limit, 100) : 10;          // cap to 100
            $offset = $offset >= 0 ? $offset : 0;

            $orderId     = $request->input('order_id');
            $userName    = $request->input('user_name');
            $status      = $request->input('status');              // pending|completed|cancelled|refunded
            $payStatus   = $request->input('payment_status');      // pending|paid|failed
            $userType    = strtolower(trim((string) $request->input('user_type', '')));

            // Date filters (single OR range)
            $date       = $request->input('date');                 // "YYYY-MM-DD"
            $dateFromIn = $request->input('date_from');            // "YYYY-MM-DD"
            $dateToIn   = $request->input('date_to');              // "YYYY-MM-DD"

            // Sorting (whitelisted)
            $sortBy  = $request->input('sort_by', 'id');
            $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $sortable = ['id','created_at','total_amount','status','payment_status'];
            if (!in_array($sortBy, $sortable, true)) {
                $sortBy = 'id';
            }

            // 3) Build query
            $query = OrderModel::with([
                'user:id,name,email,mobile,role,selected_type,gstin',
                'payments:id,order_id,amount,status,created_at',
                // ✅ add this
                'latestShipment:id,order_id,courier,status,customer_name,customer_phone,customer_email,awb_no,courier_reference,booked_at'
            ]);

            // Order ID filter
            if (!is_null($orderId) && $orderId !== '') {
                $query->where('id', (int) $orderId);
            }

            // User name filter
            if (!empty($userName)) {
                $query->whereHas('user', function ($q) use ($userName) {
                    $q->where('name', 'like', '%' . $userName . '%');
                });
            }

            // Status filter
            if (!empty($status)) {
                $query->where('status', $status);
            }

            // Payment status filter
            if (!empty($payStatus)) {
                $query->where('payment_status', $payStatus);
            }

            // User Type filter (customer | architect | dealer)
            if ($userType !== '' && in_array($userType, ['customer','architect','dealer'], true)) {
                $query->whereHas('user', function ($uq) use ($userType) {
                    $uq->whereRaw('LOWER(role) = ?', [$userType]);
                });
            }

            // Date / Range filter (inclusive, with start/end of day)
            if (!empty($dateFromIn) || !empty($dateToIn)) {
                // Range mode
                $start = $dateFromIn ? Carbon::parse($dateFromIn)->startOfDay() : Carbon::minValue();
                $end   = $dateToIn   ? Carbon::parse($dateToIn)->endOfDay()     : Carbon::maxValue();
                $query->whereBetween('created_at', [$start, $end]);
            } elseif (!empty($date)) {
                // Single day filter
                try {
                    $startOfDay = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
                    $endOfDay   = Carbon::createFromFormat('Y-m-d', $date)->endOfDay();
                    $query->whereBetween('created_at', [$startOfDay, $endOfDay]);
                } catch (\Exception $e) {
                    // fallback if Carbon parse fails
                    $query->whereDate('created_at', $date);
                }
            }


            // 4) Count first (for pagination meta)
            $total = (clone $query)->count();

            // 5) Fetch page
            $orders = $query
                ->orderBy($sortBy, $sortDir)
                ->offset($offset)
                ->limit($limit)
                ->get();

            // 6) Optional shaping (format money to 2 decimals)
            $money = fn ($v) => number_format((float) $v, 2, '.', '');
            $data  = $orders->map(function ($o) use ($money) {

                $sh = $o->latestShipment;
                $shipmentData = ['status' => 'pending']; // default if not eligible

                if ($sh) {
                    $shouldShowDetails =
                        ($sh->status === 'pending')
                        || !empty($sh->awb_no)
                        || !empty($sh->booked_at);

                    if ($shouldShowDetails) {
                        $shipmentData = [
                            'id'             => $sh->id,
                            'order_id'        => $sh->order_id,
                            'status'          => $sh->status,
                            'courier'         => $sh->courier,
                            'customer_name'   => $sh->customer_name,
                            'customer_phone'  => $sh->customer_phone,
                            'customer_email'  => $sh->customer_email,
                            'awb_no'          => $sh->awb_no ?: null,
                            'courier_reference' => $sh->courier_reference ?: null,
                            'booked_at'       => optional($sh->booked_at)->toIso8601String(),
                        ];
                    }
                }

                return [
                    'id'                => $o->id,
                    'total_amount'      => $money($o->total_amount),
                    'status'            => $o->status,
                    'payment_status'    => $o->payment_status,
                    'shipping_address'  => $o->shipping_address,
                    'razorpay_order_id' => $o->razorpay_order_id,
                    'created_at'        => optional($o->created_at)->toIso8601String(),
                    'user'              => $o->relationLoaded('user') && $o->user ? [
                        'id'            => $o->user->id,
                        'name'          => $o->user->name,
                        'email'         => $o->user->email,
                        'mobile'        => $o->user->mobile,
                        'role'          => $o->user->role,
                        'selected_type' => $o->user->selected_type,
                        'gstin'         => $o->user->gstin,
                    ] : null,
                    'payments'          => $o->relationLoaded('payments')
                        ? $o->payments->map(fn($p) => [
                            'id'         => $p->id,
                            'amount'     => $money($p->amount),
                            'status'     => $p->status,
                            'created_at' => optional($p->created_at)->toIso8601String(),
                        ])->values()
                        : [],
                    'shipment'           => $shipmentData,   // ✅ ADD THIS
                ];
            })->values();

            // 7) Meta (pagination + filters echo)
            $meta = [
                'limit'     => $limit,
                'offset'    => $offset,
                'count'     => $data->count(),
                'total'     => $total,
                'has_more'  => ($offset + $limit) < $total,
                'sort_by'   => $sortBy,
                'sort_dir'  => $sortDir,
                'filters'   => [
                    'order_id'       => $orderId,
                    'user_name'      => $userName,
                    'status'         => $status,
                    'payment_status' => $payStatus,
                    'date'           => $date,
                    'date_from'      => $dateFromIn,
                    'date_to'        => $dateToIn,
                    'user_type'      => $userType, 
                ],
            ];

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Orders fetched successfully!',
                'meta'    => $meta,
                'data'    => $data,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Error fetching orders: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }
    public function fetchOrderDetails($id)
    {
        // Money format helpers
        $money = function ($v) {
            return number_format((float) $v, 2, '.', '');
        };
        $mulMoney = function ($a, $b) use ($money) {
            if (function_exists('bcmul')) {
                $prod = bcmul((string)$a, (string)((int)$b), 2);
                return number_format((float)$prod, 2, '.', '');
            }
            $raw = ((float)$a) * ((int)$b);
            return number_format(round($raw + 1e-8, 2), 2, '.', '');
        };

        // Split gross (inclusive) into base and tax at a rate
        $splitInclusive = function ($gross, $rate = 18) use ($money) {
            $gross = (string)$gross;
            $factor = 1 + ($rate / 100); // e.g. 1.18

            if (function_exists('bcdiv') && function_exists('bcsub')) {
                $base = bcdiv($gross, (string)$factor, 2);          // base = gross / 1.18
                $tax  = bcsub($gross, $base, 2);                    // tax  = gross - base
            } else {
                $g = (float)$gross;
                $b = $g / $factor;
                $t = $g - $b;
                // round to 2 to avoid float tails
                $base = number_format(round($b + 1e-8, 2), 2, '.', '');
                $tax  = number_format(round($t + 1e-8, 2), 2, '.', '');
            }

            return ['base' => $money($base), 'tax' => $money($tax)];
        };

        // Eager-load everything
        $order = OrderModel::with([
            'user' => function ($q) {
                $q->select('id','name','email','mobile','role','selected_type','gstin');
            },
            'items' => function ($iq) {
                $iq->select('id','order_id','product_id','variant_id','quantity','price')
                ->with([
                    'product' => function ($pq) {
                        $pq->select('id','name','slug');
                    },
                    'variant' => function ($vq) {
                        $vq->select('id','product_id','variant_value','regular_price');
                    },
                ]);
            },
            'payments' => function ($pq) {
                $pq->select('id','order_id','amount','status','created_at');
            },
            
        ])->find($id);

        if (!$order) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Order not found.',
                'data' => [],
            ], 404);
        }

        // Compute totals split (inclusive -> base + tax)
        $split = $splitInclusive($order->total_amount, 18); // 18% GST

        // Shape data
        $data = [
            'id'                => $order->id,
            'total_amount'      => $money($order->total_amount), // gross (incl. GST)
            'total'             => $split['base'],               // base amount (excl. GST)
            'tax'               => $split['tax'],                // GST portion
            'status'            => $order->status,
            'payment_status'    => $order->payment_status,
            'shipping_address'  => $order->shipping_address,
            'razorpay_order_id' => $order->razorpay_order_id,
            'created_at'        => optional($order->created_at)->toIso8601String(),
            'user'              => $order->user ? [
                'id'            => $order->user->id,
                'name'          => $order->user->name,
                'email'         => $order->user->email,
                'mobile'        => $order->user->mobile,
                'role'          => $order->user->role,
                'selected_type' => $order->user->selected_type,
                'gstin'         => $order->user->gstin,
            ] : null,
            'items'             => $order->items->map(function ($it) use ($money, $mulMoney) {
                return [
                    'id'         => $it->id,
                    'product_id' => $it->product_id,
                    'variant_id' => $it->variant_id,
                    'quantity'   => (int) $it->quantity,
                    'price'      => $money($it->price),
                    'subtotal'   => $mulMoney($it->price, $it->quantity),
                    'product'    => $it->product ? [
                        'id'   => $it->product->id,
                        'name' => $it->product->name,
                        'slug' => $it->product->slug,
                    ] : null,
                    'variant'    => $it->variant ? [
                        'id'            => $it->variant->id,
                        'variant_value' => $it->variant->variant_value,
                        'regular_price' => $money($it->variant->regular_price),
                    ] : null,
                ];
            })->values(),
            'payments'          => $order->payments->map(function ($p) use ($money) {
                return [
                    'id'         => $p->id,
                    'amount'     => $money($p->amount),
                    'status'     => $p->status,
                    'created_at' => optional($p->created_at)->toIso8601String(),
                ];
            })->values(),
        ];

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Order details fetched successfully!',
            'data'    => $data,
        ], 200);
    }

}
