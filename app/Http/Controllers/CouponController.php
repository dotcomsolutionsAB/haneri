<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CouponModel;

class CouponController extends Controller
{
    //
    // List all coupons
    public function fetchAll(Request $request)
    {
        $limit  = max(1, (int) $request->input('limit', 10));
        $offset = max(0, (int) $request->input('offset', 0));

        $coupon_code = trim((string) $request->input('coupon_code', ''));
        $status      = trim((string) $request->input('status', ''));

        $q = CouponModel::query();

        // ✅ Filter: coupon_code (partial match)
        if ($coupon_code !== '') {
            $q->where('coupon_code', 'like', '%' . $coupon_code . '%');
        }

        // ✅ Filter: status (active / inactive)
        if ($status !== '') {
            if (!in_array($status, ['active', 'inactive'], true)) {
                return response()->json([
                    'code'    => 422,
                    'success' => false,
                    'message' => 'Invalid status. Allowed: active, inactive.',
                    'data'    => [],
                ], 422);
            }
            $q->where('status', $status);
        }

        // ✅ Total count before pagination
        $total = (clone $q)->count();

        // ✅ Pagination
        $coupons = $q->orderByDesc('id')
            ->limit($limit)
            ->offset($offset)
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
                'total'   => $total,
                'count'   => $coupons->count(),
                'limit'   => $limit,
                'offset'  => $offset,
                'coupons' => $coupons,
            ],
        ], 200);
    }

    // Add a new coupon
    public function create(Request $request)
    {
        // Validate the incoming data
        $validator = \Validator::make($request->all(), [
            'coupon_code'     => 'required|string|max:100|unique:t_coupons,coupon_code',
            'user_id'         => 'nullable|integer', // optionally you can add exists:users,id
            'discount_type'   => 'required|in:percentage,price',
            'discount_value'  => 'required|numeric|min:0',
            'count'           => 'required|integer|min:0',
            'validity'        => 'required|date',
            'status'          => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'data'    => $validator->errors(),
            ], 422);
        }

        // Create the coupon
        $coupon = CouponModel::create([
            'coupon_code'    => $request->input('coupon_code'),
            'user_id'        => $request->input('user_id', null),
            'discount_type'  => $request->input('discount_type'),
            'discount_value' => $request->input('discount_value'),
            'count'          => (int) $request->input('count', 0),
            'validity'       => $request->input('validity'),
            'status'         => $request->input('status', 'active'),
        ]);

        // Hide unwanted fields (optional)
        $coupon->makeHidden(['id', 'created_at', 'updated_at']);

        return response()->json([
            'code'    => 201,
            'success' => true,
            'message' => 'Coupon created successfully!',
            'data'    => $coupon,
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

    // Update Coupon
    public function update(Request $request, $id)
    {
        // Find coupon
        $coupon = CouponModel::find($id);

        if (!$coupon) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Coupon not found.',
                'data'    => [],
            ], 404);
        }

        // Validate
        $validator = \Validator::make($request->all(), [
            'coupon_code'    => 'sometimes|required|string|max:100|unique:t_coupons,coupon_code,' . $id,
            'user_id'        => 'nullable|integer', // optionally: exists:users,id
            'discount_type'  => 'sometimes|required|in:percentage,price',
            'discount_value' => 'sometimes|required|numeric|min:0',
            'count'          => 'sometimes|required|integer|min:0',
            'validity'       => 'sometimes|required|date',
            'status'         => 'sometimes|required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'data'    => $validator->errors(),
            ], 422);
        }

        // Apply updates (only provided fields)
        $coupon->fill($validator->validated());
        $coupon->save();

        $coupon->makeHidden(['id', 'created_at', 'updated_at']);

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Coupon updated successfully!',
            'data'    => $coupon,
        ], 200);
    }


}
