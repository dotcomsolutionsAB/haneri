<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CouponModel;

class CouponController extends Controller
{
    //
    // List all coupons
    public function fetchAll()
    {
        $coupons = CouponModel::query()
            ->orderByDesc('id')
            ->get()
            ->makeHidden(['id', 'created_at', 'updated_at']);

        if ($coupons->isEmpty()) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'No coupons found.',
                'data'    => [],
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Coupons retrieved successfully!',
            'data'    => [
                'count'   => $coupons->count(),
                'coupons' => $coupons,
            ],
        ], 200);
    }

    // Add a new coupon
    public function create(Request $request)
    {
        // Validate the incoming data
        $request->validate([
            'coupon_code'    => 'required|string|max:100|unique:t_coupons,coupon_code',
            'user_id'        => 'nullable|integer|exists:users,id',
            'discount_type'  => 'required|in:percentage,price',
            'discount_value' => 'required|numeric|min:0',
            'count'          => 'nullable|integer|min:0', // if not passed, default handled below
            'validity'       => 'required|date', // you can add after_or_equal:today if you want
        ]);

        // Create the coupon
        $coupon = CouponModel::create([
            'coupon_code'    => $request->input('coupon_code'),
            'user_id'        => $request->input('user_id'),
            'discount_type'  => $request->input('discount_type'),
            'discount_value' => $request->input('discount_value'),
            'count'          => $request->input('count', 0),
            'validity'       => $request->input('validity'),
        ]);

        // Remove extra fields from response
        $data = $coupon->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        // Return the newly created coupon
        return response()->json([
            'message' => 'Coupon created successfully!',
            'data'    => $data
        ], 201);
    }

    // Delete a coupon
    public function delete($id)
    {
        // Find the coupon by ID
        $coupon = CouponModel::find($id);

        // If coupon doesn't exist, return error
        if (!$coupon) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Coupon not found.',
                'data'    => [],
            ], 404);
        }

        // Delete the coupon
        $coupon->delete();

        // Return success message
        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Coupon deleted successfully!',
            'data'    => [],
        ], 200);
    }

}
