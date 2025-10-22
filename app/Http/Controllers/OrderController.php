<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\CartModel;
use App\Models\User;
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
    public function fetchOrders(Request $request)
    {
        try {
            // Ensure the user is an admin
            $user = Auth::user();
            if ($user->role !== 'admin') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Set default limit and offset
            $limit = $request->input('limit', 10); // Default limit is 10
            $offset = $request->input('offset', 0); // Default offset is 0

            // Query Orders with Filters
            $query = OrderModel::with(['user', 'payments']);

            // Filter by Order ID
            if ($request->has('order_id')) {
                $query->where('id', $request->order_id);
            }

            // Filter by Date
            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }

            // Filter by User Name
            if ($request->has('user_name')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->user_name . '%');
                });
            }

            // Filter by Status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Get Total Orders Count (for pagination)
            $totalOrders = $query->count();

            // Get Filtered Orders with Pagination
            $orders = $query->offset($offset)->limit($limit)->get();

            // Return Response
            return response()->json([
                'success' => true,
                'message' => 'Orders fetched successfully!',
                'total_orders' => $totalOrders,
                'data' => $orders,
            ], 200);

        } catch (\Exception $e) {
            // Handle Errors
            return response()->json([
                'success' => false,
                'message' => 'Error fetching orders: ' . $e->getMessage(),
            ], 500);
        }
    }

}
