<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CouponModel;

class CouponController extends Controller
{
    //
    // List all coupons
    public function index()
    {
        $coupons = CouponModel::all();  // Get all coupons

        return $coupons->isNotEmpty()
            ? response()->json(['message' => 'Coupons retrieved successfully!', 'data' => $coupons->makeHidden(['id', 'created_at', 'updated_at']), 'count' => count($coupons)], 200)
            : response()->json(['message' => 'No coupons found.'], 400);
    }

    // Add a new coupon
    public function store(Request $request)
    {
        // Validate the incoming data
        $request->validate([
            'code' => 'required|string|unique:t_coupons,code', // Ensure the code is unique
            'discount_type' => 'required|in:fixed,percentage', // Ensure the discount type is valid
            'discount_value' => 'required|numeric|min:0', // Ensure the discount value is valid
            'expiration_date' => 'required|date|after:today', // Expiration date must be after today
            'usage_limit' => 'nullable|integer|min:1', // Usage limit (optional)
        ]);

        // Create the coupon
        $coupon = CouponModel::create([
            'code' => $request->input('code'),
            'discount_type' => $request->input('discount_type'),
            'discount_value' => $request->input('discount_value'),
            'expiration_date' => $request->input('expiration_date'),
            'usage_limit' => $request->input('usage_limit', null), // Default to null if not provided
            'used_count' => 0, // Initialize used count to 0
        ]);

        unset($coupon['id'], $coupon['created_at'], $coupon['updated_at']);

        // Return the newly created coupon
        return response()->json(['message' => 'Coupon created successfully!', 'data' => $coupon], 201);
    }

    // Delete a coupon
    public function destroy($id)
    {
        // Find the coupon by ID
        $coupon = CouponModel::find($id);

        // If coupon doesn't exist, return error
        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        // Delete the coupon
        $coupon->delete();

        // Return success message
        return response()->json(['message' => 'Coupon deleted successfully!'], 200);
    }
}
