<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartModel;
use App\Models\UsersDiscountModel;
use App\Models\UploadModel;
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
            'cart_id' => 'nullable|string'
        ]);

        $token = $request->bearerToken();
        $user = null;

        if ($token) {
            $user = Auth::guard('sanctum')->user();
        }

        if ($user) {
            $userId = $user->id;
        } else {
            $cartId = $request->input('cart_id');
            if (!$cartId) {
                do {
                    $cartId = (string) Str::uuid();
                } while (CartModel::where('user_id', $cartId)->exists());
            }
            $userId = $cartId;
        }

        // FIND if item already in cart
        $existingCartItem = CartModel::where('user_id', $userId)
            ->where('product_id', $request->input('product_id'))
            ->where(function ($q) use ($request) {
                if ($request->filled('variant_id')) {
                    $q->where('variant_id', $request->input('variant_id'));
                } else {
                    $q->whereNull('variant_id');
                }
            })
            ->first();

        if ($existingCartItem) {
            // Update the quantity
            $existingCartItem->quantity += $request->input('quantity');
            $existingCartItem->save();
            $cart = $existingCartItem;
        } else {
            // Create new cart row
            $cart = CartModel::create([
                'user_id' => $userId,
                'product_id' => $request->input('product_id'),
                'variant_id' => $request->input('variant_id', null),
                'quantity' => $request->input('quantity'),
            ]);
        }

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
        ->map(function ($cartItem) use ($user) {
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

        // Get variant_value from ProductVariantModel
        $variantType = $cartItem->variant->variant_value;

        // Check if the user has a discount in UsersDiscountModel first
        $discount = UsersDiscountModel::where('user_id', $user->id)
            ->where('product_variant_id', $cartItem->variant->id)
            ->value('discount');

        if ($discount === null) {
            // If no discount found, fall back to variant-based discount
            switch ($user->role) {
                case 'customer':
                    $discount = $cartItem->variant->customer_discount;
                    break;
                case 'dealer':
                    $discount = $cartItem->variant->dealer_discount;
                    break;
                case 'architect':
                    $discount = $cartItem->variant->architect_discount;
                    break;
                default:
                    $discount = 0;
                    break;
            }
        }

        $cartItem->selling_price = $this->price($cartItem->variant->regular_price, $discount);

        // Get the file URLs from the UploadModel for photo_ids
        $photoIds = explode(',', $cartItem->variant->photo_id); // Split comma-separated IDs
        $fileUrls = UploadModel::whereIn('id', $photoIds)->pluck('file_path')->toArray();

            // Optionally hide fields on the cart item itself
            $cartItem->makeHidden(['created_at', 'updated_at']);

         return [
                'id' => $cartItem->id,
                'user_name' => $cartItem->user->name,
                'product_name' => $cartItem->product->name,
                'variant_value' => $variantType,
                'selling_price' => $cartItem->selling_price,
                'quantity' => $cartItem->quantity,
                'file_urls' => $fileUrls,
            ];
        });

        return $cartItems->isNotEmpty()
            ? response()->json(['message' => 'Cart items fetched successfully!', 'data' => $cartItems, 'count' => count($cartItems)], 200)
            : response()->json(['message' => 'Your cart is empty.'], 400);
    }

    // Inside the CartController class

    private function price($regularPrice, $discount)
    {
        $discountedPrice = number_format($regularPrice - ($regularPrice * ($discount / 100)), 0);
        return $discountedPrice;
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
