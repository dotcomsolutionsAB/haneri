<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\CartModel;
use App\Models\User;
use Carbon\Carbon;
use DB;
use App\Http\Controllers\RazorpayController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;

class OrderController extends Controller
{
    //
    // Store a new order
    // public function store(Request $request)
    // {
    //     // Validate request data
    //     $request->validate([
    //         'status' => 'required|in:pending,completed,cancelled,refunded',
    //         'payment_status' => 'required|in:pending,paid,failed',
    //         'shipping_address' => 'required|string',
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
    //         $cartItems = CartModel::where('user_id', $user_id)->get();

    //         // Check if the cart is empty
    //         if ($cartItems->isEmpty()) {
    //             return response()->json(['message' => 'Sorry, cart is empty.'], 400);
    //         }

    //         // Calculate the total amount by iterating through the cart items
    //         $totalAmount  = 0 ;

    //         foreach($cartItems as $cartItem)
    //         {
    //             $totalAmount += $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id) *$cartItem->quantity;
    //         }


    //         // Call Razorpay Order API Before Saving Order in DB**
    //         $razorpayController = new RazorpayController(); 
    //         $razorpayRequest = new Request([
    //             'amount' => $totalAmount,
    //             'currency' => 'INR'
    //         ]);
    //         $razorpayResponse = $razorpayController->createOrder($razorpayRequest);

    //         // Decode Razorpay response
    //         $razorpayData = json_decode($razorpayResponse->getContent(), true);
    //         if (!$razorpayData['success']) {
    //             DB::rollBack();
    //             return response()->json(['message' => 'Failed to create Razorpay order.'], 500);
    //         }

    //         // Create the order record
    //         $order = OrderModel::create([
    //             'user_id' => $user_id,
    //             'total_amount' => $totalAmount,
    //             'status' => $request->input('status', 'pending'),
    //             'payment_status' => $request->input('payment_status', 'pending'),
    //             'shipping_address' => $request->input('shipping_address'),
    //             'razorpay_order_id' => $razorpayData['order']['id'],
    //         ]);

    //         // Iterate through each cart item to add it to the order items table
    //         foreach($cartItems as $cartItem)
    //         {
    //             // Create the order item record
    //             OrderItemModel::create([
    //                 'order_id' => $order->id, // Link to the created order
    //                 'product_id' => $cartItem->product_id,
    //                 'variant_id' => $cartItem->variant_id,
    //                 'quantity' => $cartItem->quantity,
    //                 'price' => $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id), // Final price per item
    //             ]);
    //         }

    //         // After successfully adding order items, delete the cart items
    //         CartModel::where('user_id', $user_id)->delete();

    //         // Commit the transaction
    //         DB::commit();

    //         // Build line items for the email (with product/variant names)
    //         $items = OrderItemModel::with(['product:id,name', 'variant:id,name'])
    //             ->where('order_id', $order->id)
    //             ->get()
    //             ->map(function($it) {
    //                 return [
    //                     'name'    => optional($it->product)->name ?? ('Product #'.$it->product_id),
    //                     'variant' => optional($it->variant)->name,
    //                     'qty'     => (int) $it->quantity,
    //                     'price'   => (float) $it->price,
    //                     'total'   => (float) $it->price * (int) $it->quantity,
    //                 ];
    //             })
    //             ->toArray();

    //         // Send confirmation email (do not block order if email fails)
    //         try {
    //             Mail::to($orderUser->email)->send(new OrderPlacedMail($orderUser, $order, $items));
    //         } catch (\Throwable $e) {
    //             \Log::warning('OrderPlacedMail failed for order '.$order->id.': '.$e->getMessage());
    //         }

    //         // Build line items for the email (with product/variant names)
    //         $items = OrderItemModel::with(['product:id,name', 'variant:id,name'])
    //             ->where('order_id', $order->id)
    //             ->get()
    //             ->map(function($it) {
    //                 return [
    //                     'name'    => optional($it->product)->name ?? ('Product #'.$it->product_id),
    //                     'variant' => optional($it->variant)->name,
    //                     'qty'     => (int) $it->quantity,
    //                     'price'   => (float) $it->price,
    //                     'total'   => (float) $it->price * (int) $it->quantity,
    //                 ];
    //             })
    //             ->toArray();

    //         // Send confirmation email (do not block order if email fails)
    //         try {
    //             Mail::to($orderUser->email)->send(new OrderPlacedMail($orderUser, $order, $items));
    //         } catch (\Throwable $e) {
    //             \Log::warning('OrderPlacedMail failed for order '.$order->id.': '.$e->getMessage());
    //         }

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

    // Helper function to get the final price for a product and its variant
    // private function getFinalPrice($product_id, $variant_id = null)
    // {
    //     // Assuming we have a method to fetch the product price and variant price
    //     $product = \App\Models\ProductModel::find($product_id);

    //     if ($variant_id) {
    //         // Assuming you have a method for variant price, like `getVariantPrice()`
    //         $variant = \App\Models\ProductVariantModel::find($variant_id);
    //         // return $variant ? $variant->price : $product->price;  // Fallback to product price if variant not found

    //         return $variant ? $variant->selling_price : 0;  // Fallback to product price if variant not found
    //     }

    //     return $product->price;  // Return product price if no variant
    // }

    public function store(Request $request)
    {
        // Validate request data
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled,refunded',
            'payment_status' => 'required|in:pending,paid,failed',
            'shipping_address' => 'required|string',
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
                return response()->json(['message' => 'Sorry, cart is empty.'], 400);
            }

            // Calculate the total amount by iterating through the cart items
            $totalAmount = 0.0;

            foreach ($cartItems as $cartItem) {
                $linePrice = $this->getFinalPrice($orderUser, $cartItem->product_id, $cartItem->variant_id);
                $totalAmount += $linePrice * (int)$cartItem->quantity;
            }

            // Call Razorpay Order API Before Saving Order in DB**
            $razorpayController = new RazorpayController(); 
            $razorpayRequest = new Request([
                'amount' => $totalAmount,
                'currency' => 'INR'
            ]);
            $razorpayResponse = $razorpayController->createOrder($razorpayRequest);

            // Decode Razorpay response
            $razorpayData = json_decode($razorpayResponse->getContent(), true);
            if (!$razorpayData['success']) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to create Razorpay order.'], 500);
            }

            // Create the order record
            $order = OrderModel::create([
                'user_id' => $user_id,
                'total_amount' => $totalAmount,
                'status' => $request->input('status', 'pending'),
                'payment_status' => $request->input('payment_status', 'pending'),
                'shipping_address' => $request->input('shipping_address'),
                'razorpay_order_id' => $razorpayData['order']['id'],
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
            // CartModel::where('user_id', $user_id)->delete();
            CartModel::where('user_id', (string)$user_id)->delete();

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

            // Send confirmation email (do not block order if email fails)
            try {
                Mail::to($orderUser->email)->send(new OrderPlacedMail($orderUser, $order, $items));
            } catch (\Throwable $e) {
                \Log::warning('OrderPlacedMail failed for order '.$order->id.': '.$e->getMessage());
            }

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

    // View all orders for a user
    public function index(Request $request)
    {
        $user = Auth::user(); 

        // If the user is an admin, validate user_id in the request
        if ($user->role == 'admin') {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
            ]);
            $user_id =  $request->input('user_id');
        } else {
            $user_id =  $user->id;
        }

        // Fetch all orders for the user
        $orders = OrderModel::with(['items', 'user'])
                            -> where('user_id', $user_id)
                            ->get()
                            ->map(function ($order) {
                            // Make sure to hide the unwanted fields from the user and items
                            if ($order->items) {
                                $order->items->makeHidden(['id', 'created_at', 'updated_at']);
                            }
                            if ($order->user) {
                                $order->user->makeHidden(['id', 'created_at', 'updated_at']);
                            }
                            // Optionally hide fields from the order
                            $order->makeHidden(['id', 'created_at', 'updated_at']);
                            return $order;
                        });

        return $orders->isNotEmpty()
            ? response()->json(['message' => 'Orders fetched successfully!', 'data' => $orders, 'count' => count($orders)], 200)
            : response()->json(['message' => 'No orders found.'], 400);
    }

    // View details of a single order
    public function show($id)
    {
        $user = Auth::user();

        // Fetch the order by ID for the user
        $get_order = OrderModel::with(['items', 'user'])
                            ->where('user_id', $user->id)
                            ->get()
                            ->map(function ($order) {
                                // Make sure to hide the unwanted fields from the user and items
                                if ($order->items) {
                                    $order->items->makeHidden(['id', 'created_at', 'updated_at']);
                                }
                                if ($order->user) {
                                    $order->user->makeHidden(['id', 'created_at', 'updated_at']);
                                }
                                // Optionally hide fields from the order
                                $order->makeHidden(['id', 'created_at', 'updated_at']);
                                return $order;
                            });

        if (!$get_order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Hide unnecessary fields
        $get_order->makeHidden(['id', 'created_at', 'updated_at']);

        return response()->json(['message' => 'Order details fetched successfully!', 'data' => $get_order], 200);
    }

    // delete an order
    public function delete($orderId)
    {
        try {
            // Start transaction
            DB::beginTransaction();

            // Fetch the order
            $order = OrderModel::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found!',
                ], 404);
            }

            // Delete related order items
            OrderItemModel::where('order_id', $orderId)->delete();

            // Delete the order
            $order->delete();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order and corresponding items deleted successfully!',
            ], 200);
        } catch (\Exception $e) {
            // Rollback transaction in case of failure
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // for all orders
    /**
     * Fetch Orders for Admin with Filters
     */
    // public function fetchOrders(Request $request)
    // {
    //     try {
    //         // Ensure the user is an admin
    //         $user = Auth::user();
    //         if ($user->role !== 'admin') {
    //             return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    //         }

    //         // Set default limit and offset
    //         $limit = $request->input('limit', 10); // Default limit is 10
    //         $offset = $request->input('offset', 0); // Default offset is 0

    //         // Query Orders with Filters
    //         $query = OrderModel::with(['user', 'payments']);

    //         // Filter by Order ID
    //         if ($request->has('order_id')) {
    //             $query->where('id', $request->order_id);
    //         }

    //         // Filter by Date or Date Range
    //         if ($request->filled('date_from') && $request->filled('date_to')) {
    //             $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
    //         } elseif ($request->filled('date')) {
    //             $query->whereDate('created_at', $request->date);
    //         }


    //         // Filter by User Name
    //         if ($request->has('user_name')) {
    //             $query->whereHas('user', function ($q) use ($request) {
    //                 $q->where('name', 'like', '%' . $request->user_name . '%');
    //             });
    //         }

    //         // Filter by Status
    //         if ($request->has('status')) {
    //             $query->where('status', $request->status);
    //         }

    //         // Get Total Orders Count (for pagination)
    //         $totalOrders = $query->count();

    //         // Get Filtered Orders with Pagination
    //         $orders = $query->offset($offset)->limit($limit)->get();

    //         // Return Response
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Orders fetched successfully!',
    //             'total_orders' => $totalOrders,
    //             'data' => $orders,
    //         ], 200);

    //     } catch (\Exception $e) {
    //         // Handle Errors
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error fetching orders: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }
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
                'payments:id,order_id,amount,status,created_at'
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

            // Date / Range filter (inclusive, with start/end of day)
            if (!empty($dateFromIn) || !empty($dateToIn)) {
                // Range mode
                $start = $dateFromIn ? Carbon::parse($dateFromIn)->startOfDay() : Carbon::minValue();
                $end   = $dateToIn   ? Carbon::parse($dateToIn)->endOfDay()     : Carbon::maxValue();
                $query->whereBetween('created_at', [$start, $end]);
            } elseif (!empty($date)) {
                // Single day
                $day = Carbon::parse($date);
                $query->whereBetween('created_at', [$day->startOfDay(), $day->endOfDay()]);
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

    // public function fetchOrderDetails($id)
    // {
    //     // Helpers for money formatting (keep consistent with your profile API)
    //     $money = function ($v) {
    //         return number_format((float) $v, 2, '.', '');
    //     };
    //     $mulMoney = function ($a, $b) use ($money) {
    //         if (function_exists('bcmul')) {
    //             $prod = bcmul((string)$a, (string)((int)$b), 2);
    //             return number_format((float)$prod, 2, '.', '');
    //         }
    //         $raw = ((float)$a) * ((int)$b);
    //         return number_format(round($raw + 1e-8, 2), 2, '.', '');
    //     };

    //     // Eager-load everything
    //     $order = OrderModel::with([
    //         'user' => function ($q) {
    //             $q->select('id','name','email','mobile','role','selected_type','gstin');
    //         },
    //         'items' => function ($iq) {
    //             $iq->select('id','order_id','product_id','variant_id','quantity','price')
    //             ->with([
    //                 'product' => function ($pq) {
    //                     $pq->select('id','name','slug');
    //                 },
    //                 'variant' => function ($vq) {
    //                     $vq->select('id','product_id','variant_value','regular_price');
    //                 },
    //             ]);
    //         },
    //         'payments' => function ($pq) {
    //             $pq->select('id','order_id','amount','status','created_at');
    //         },
    //     ])->find($id);

    //     if (!$order) {
    //         return response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'Order not found.',
    //             'data' => [],
    //         ], 404);
    //     }

    //     // Shape data
    //     $data = [
    //         'id'                => $order->id,
    //         'total_amount'      => $money($order->total_amount),
    //         'status'            => $order->status,
    //         'payment_status'    => $order->payment_status,
    //         'shipping_address'  => $order->shipping_address,
    //         'razorpay_order_id' => $order->razorpay_order_id,
    //         'created_at'        => optional($order->created_at)->toIso8601String(),
    //         'user'              => $order->user ? [
    //             'id'            => $order->user->id,
    //             'name'          => $order->user->name,
    //             'email'         => $order->user->email,
    //             'mobile'        => $order->user->mobile,
    //             'role'          => $order->user->role,
    //             'selected_type' => $order->user->selected_type,
    //             'gstin'         => $order->user->gstin,
    //         ] : null,
    //         'items'             => $order->items->map(function ($it) use ($money, $mulMoney) {
    //             return [
    //                 'id'         => $it->id,
    //                 'product_id' => $it->product_id,
    //                 'variant_id' => $it->variant_id,
    //                 'quantity'   => (int) $it->quantity,
    //                 'price'      => $money($it->price),
    //                 'subtotal'   => $mulMoney($it->price, $it->quantity),
    //                 'product'    => $it->product ? [
    //                     'id'   => $it->product->id,
    //                     'name' => $it->product->name,
    //                     'slug' => $it->product->slug,
    //                 ] : null,
    //                 'variant'    => $it->variant ? [
    //                     'id'            => $it->variant->id,
    //                     'variant_value' => $it->variant->variant_value,
    //                     'regular_price' => $money($it->variant->regular_price),
    //                 ] : null,
    //             ];
    //         })->values(),
    //         'payments'          => $order->payments->map(function ($p) use ($money) {
    //             return [
    //                 'id'         => $p->id,
    //                 'amount'     => $money($p->amount),
    //                 'status'     => $p->status,
    //                 'created_at' => optional($p->created_at)->toIso8601String(),
    //             ];
    //         })->values(),
    //     ];

    //     return response()->json([
    //         'code'    => 200,
    //         'success' => true,
    //         'message' => 'Order details fetched successfully!',
    //         'data'    => $data,
    //     ], 200);
    // }
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


    public function updateOrderStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status'           => 'nullable|in:pending,completed,cancelled,refunded',
            'payment_status'   => 'nullable|in:pending,paid,failed',
            'delivery_status'  => 'nullable|in:pending,accepted,arrived,completed,cancelled',
        ]);

        $order = OrderModel::find($id);

        if (!$order) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Order not found.',
                'data'    => [],
            ], 404);
        }

        // Update only provided fields
        $order->update(array_filter([
            'status'          => $validated['status'] ?? $order->status,
            'payment_status'  => $validated['payment_status'] ?? $order->payment_status,
            'delivery_status' => $validated['delivery_status'] ?? $order->delivery_status,
        ]));

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Order status updated successfully!',
            'data'    => [
                'id'              => $order->id,
                'status'          => $order->status,
                'payment_status'  => $order->payment_status,
                'delivery_status' => $order->delivery_status,
                'updated_at'      => $order->updated_at->toIso8601String(),
            ],
        ], 200);
    }


}
