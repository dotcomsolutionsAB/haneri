<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\CartModel;
use DB;
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

            // Create the order record
            $order = OrderModel::create([
                'user_id' => $user_id,
                'total_amount' => $totalAmount,
                'status' => $request->input('status', 'pending'),
                'payment_status' => $request->input('payment_status', 'pending'),
                'shipping_address' => $request->input('shipping_address'),
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

            // Exclude unwanted fields from the response
            unset($order['id'], $order['created_at'], $order['updated_at']);

            // Return success response
            return response()->json(['message' => 'Order created successfully!', 'data' => $order], 201);
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
            return $variant ? $variant->price : $product->price;  // Fallback to product price if variant not found
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
        $orders = OrderModel::where('user_id', $user_id)->get()
            ->map(function ($order) {
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
        $order = OrderModel::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Hide unnecessary fields
        $order->makeHidden(['id', 'created_at', 'updated_at']);

        return response()->json(['message' => 'Order details fetched successfully!', 'data' => $order], 200);
    }
}
