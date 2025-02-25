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

class OrderController extends Controller
{
    //
    // Store a new order
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
            $cartItems = CartModel::where('user_id', $user_id)->get();

            // Check if the cart is empty
            if ($cartItems->isEmpty()) {
                return response()->json(['message' => 'Sorry, cart is empty.'], 400);
            }

            // Calculate the total amount by iterating through the cart items
            $totalAmount  = 0 ;

            foreach($cartItems as $cartItem)
            {
                $totalAmount += $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id) *$cartItem->quantity;
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
            foreach($cartItems as $cartItem)
            {
                // Create the order item record
                OrderItemModel::create([
                    'order_id' => $order->id, // Link to the created order
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'quantity' => $cartItem->quantity,
                    'price' => $this->getFinalPrice($cartItem->product_id, $cartItem->variant_id), // Final price per item
                ]);
            }

            // After successfully adding order items, delete the cart items
            CartModel::where('user_id', $user_id)->delete();

            // Commit the transaction
            DB::commit();

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


    // Helper function to get the final price for a product and its variant
    private function getFinalPrice($product_id, $variant_id = null)
    {
        // Assuming we have a method to fetch the product price and variant price
        $product = \App\Models\ProductModel::find($product_id);

        if ($variant_id) {
            // Assuming you have a method for variant price, like `getVariantPrice()`
            $variant = \App\Models\ProductVariantModel::find($variant_id);
            // return $variant ? $variant->price : $product->price;  // Fallback to product price if variant not found

            return $variant ? $variant->selling_price : 0;  // Fallback to product price if variant not found
        }

        return $product->price;  // Return product price if no variant
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
}
