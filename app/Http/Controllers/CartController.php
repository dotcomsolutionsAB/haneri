<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartModel;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Str;
use Illuminate\Support\Facades\Cookie;
use Hash;

class CartController extends Controller
{
    //
    // Store
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:t_products,id',
            'variant_id' => 'nullable|integer|exists:t_product_variants,id',
            'quantity' => 'required|integer|min:1',
            // Note: cart_id is optional for guest users
            'cart_id' => 'nullable|string'
        ]);

        // Manually check for Bearer Token (optional)
        $token = $request->bearerToken();
        $user = null; // Initialize the $user variable to avoid "undefined variable" error

        if ($token) {
            $user = Auth::guard('sanctum')->user(); // Manually authenticate using Sanctum
        }

        if ($user) {
            // If user is logged in, use their ID
            $userId = $user->id;
        } else {
            // For guest users, get cart_id from request input
            $cartId = $request->input('cart_id');

            // If no cart_id is provided, generate a new one
            if (!$cartId) {
                do {
                    $cartId = (string) Str::uuid();
                } while (CartModel::where('user_id', $cartId)->exists());
            }
            // Use the cart_id as the user_id for guest users
            $userId = $cartId;        
        }

        $cart = CartModel::create([
            'user_id' => $userId,
            'product_id' => $request->input('product_id'),
            'variant_id' => $request->input('variant_id', null),
            'quantity' => $request->input('quantity'),
        ]);

        unset($cart['id'], $cart['created_at'], $cart['updated_at']);

        return response()->json(['message' => 'Item added to cart successfully!', 'data' => $cart], 201);
    }

    // View All Cart Items for a User
    public function index(Request $request)
    {

        // Manually check for Bearer Token (optional)
        $token = $request->bearerToken();
        $user = null; // Initialize the $user variable to avoid "undefined variable" error

        if ($token) {
            $user = Auth::guard('sanctum')->user(); // Manually authenticate using Sanctum
        }

        if ($user) {

            if ($user->role == 'admin') {
                $request->validate([
                    'user_id' => 'required|integer|exists:users,id',
                ]);  
                $user_id =  $request->input('user_id');
            }
    
            else {
                $user_id =  $user->id;
            }

            $userId = $user->id;
        } else {
            // Retrieve the cart_id from cookies
            //$cartId = $request->cookie('cart_id');

            // Replace with Normal Request Input
            $cartId = $request->input('cart_id');

            // Check if the cart_id exists in the cookies
            if (!$cartId) {
                return response()->json(['message' => 'Cart not found.'], 404);
            }

            $user_id = $cartId;
        }

        // Fetch cart items associated with this cart_id (for guest users)
        $cartItems = CartModel::where('user_id', $user_id)->get();

        $cartItems = CartModel::with(['user', 'product', 'variant']) // Assuming relationships are defined
        ->where('user_id', $user_id)
        ->get()
        ->map(function ($cartItem) {
        // Make sure to hide the unwanted fields from the product and variant
        if ($cartItem->user) {
            $cartItem->user->makeHidden(['created_at', 'updated_at']);
        }

        if ($cartItem->product) {
            $cartItem->product->makeHidden(['created_at', 'updated_at']);
        }

        if ($cartItem->variant) {
            $cartItem->variant->makeHidden(['created_at', 'updated_at']);
        }

        // Optionally hide fields on the cart item itself
        $cartItem->makeHidden(['created_at', 'updated_at']);

        return $cartItem;
        });

        return $cartItems->isNotEmpty()
            ? response()->json(['message' => 'Cart items fetched successfully!', 'data' => $cartItems, 'count' => count($cartItems)], 200)
            : response()->json(['message' => 'Your cart is empty.'], 400);
    }

    // Update Cart Item
    public function update(Request $request, $id)
    {
        // $user = Auth::user(); 
        // Check for Bearer Token (Authenticated User)
        $user = Auth::guard('sanctum')->user(); 
        $user_id = null;

        // if ($user->role == 'admin') {
        //     $request->validate([
        //         'user_id' => 'required|integer|exists:users,id',
        //     ]);  
        //     $user_id =  $request->input('user_id');
        // }

        // else {
        //     $user_id =  $user->id;
        // }

        if ($user) {
            // If admin, require 'user_id' in request
            if ($user->role == 'admin') {
                $request->validate([
                    'user_id' => 'required|integer|exists:users,id',
                ]);  
                $user_id = $request->input('user_id');
            } else {
                $user_id = $user->id;
            }
        } else {
            // If no Bearer Token, check for 'cart_id' cookie
            // $cartId = $request->cookie('cart_id');

            // initialize
            $cartId = null;
            // For guest users, get cart_id from request input
            $cartId = $request->input('cart_id');
    
            if (!$cartId) {
                return response()->json(['message' => 'Your cart is empty.'], 400);
            }
    
            $user_id = $cartId; // Use cart_id as user_id for guest users
        }

        $cartItem = CartModel::where('user_id', $user_id)->find($id);
        if (!$cartItem) {
            return response()->json(['message' => 'Cart item not found.'], 404);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem->update([
            'quantity' => $request->input('quantity', $cartItem->quantity),
        ]);

        unset($cartItem['id'], $cartItem['created_at'], $cartItem['updated_at']);

        return response()->json(['message' => 'Cart item updated successfully!', 'data' => $cartItem], 200);
    }

    // Delete Cart Item
    public function destroy(Request $request, $id)
    {
        // Check for Bearer Token (Authenticated User)
        $user = Auth::guard('sanctum')->user(); 
        $user_id = null;

        if ($user) {
            // If admin, require 'user_id' in request
            if ($user->role == 'admin') {
                $request->validate([
                    'user_id' => 'required|integer|exists:users,id',
                ]);  
                $user_id = $request->input('user_id');
            } else {
                $user_id = $user->id;
            }
        } else {
            // initialize
            $cartId = null;
            // For guest users, get cart_id from request input
            $cartId = $request->input('cart_id');
    
            if (!$cartId) {
                return response()->json(['message' => 'Your cart is empty.'], 400);
            }
    
            $user_id = $cartId; // Use cart_id as user_id for guest users
        }


        $cartItem = CartModel::where('user_id', $user_id)->find($id);

        if (!$cartItem) {
            return response()->json(['message' => 'Cart item not found.'], 404);
        }

        $cartItem->delete();

        return response()->json(['message' => 'Cart item deleted successfully!'], 200);
    }
}
