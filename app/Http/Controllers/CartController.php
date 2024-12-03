<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartModel;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    //
    // Store
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:t_products,id',
            'variant_id' => 'nullable|integer|exists:t_product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = CartModel::create([
            'user_id' => $request->input('user_id'),
            'product_id' => $request->input('product_id'),
            'variant_id' => $request->input('variant_id', null),
            'quantity' => $request->input('quantity'),
        ]);

        unset($cart['id'], $cart['created_at'], $cart['updated_at']);

        return response()->json(['message' => 'Item added to cart successfully!', 'data' => $cart], 201);
    }

    // View All Cart Items for a User
    public function index()
    {
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

        $cartItems = CartModel::with(['user', 'product', 'variant']) // Assuming relationships are defined
        ->where('user_id', $user_id)
        ->get()
        ->map(function ($cartItem) {
        // Make sure to hide the unwanted fields from the product and variant
        if ($cartItem->user) {
            $cartItem->user->makeHidden(['id', 'created_at', 'updated_at']);
        }

        if ($cartItem->product) {
            $cartItem->product->makeHidden(['id', 'created_at', 'updated_at']);
        }

        if ($cartItem->variant) {
            $cartItem->variant->makeHidden(['id', 'created_at', 'updated_at']);
        }

        // Optionally hide fields on the cart item itself
        $cartItem->makeHidden(['id', 'created_at', 'updated_at']);

        return $cartItem;
        });

        return $cartItems->isNotEmpty()
            ? response()->json(['message' => 'Cart items fetched successfully!', 'data' => $cartItems, 'count' => count($cartItems)], 200)
            : response()->json(['message' => 'Your cart is empty.'], 400);
    }

    // Update Cart Item
    public function update(Request $request, $id)
    {
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
    public function destroy($id)
    {
        $cartItem = CartModel::find($id);
        if (!$cartItem) {
            return response()->json(['message' => 'Cart item not found.'], 404);
        }

        $cartItem->delete();

        return response()->json(['message' => 'Cart item deleted successfully!'], 200);
    }
}
