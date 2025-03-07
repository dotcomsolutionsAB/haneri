<?php

namespace App\Http\Controllers;
use App\Models\PaymentModel;
use App\Models\OrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Auth;

class PaymentController extends Controller
{
    //
    public function store(Request $request)
    {
        // ✅ Validate Request Data
        $validator = Validator::make($request->all(), [
            'method' => 'required|string',
            'razorpay_payment_id' => 'required|string|unique:t_payment_records,razorpay_payment_id',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,failed',
            'order_id' => 'required|integer|exists:t_orders,id',
            'razorpay_order_id' => 'required|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        // if ($validator->fails()) {
        //     return response()->json(['errors' => $validator->errors()], 400);
        // }

        // ✅ Get authenticated user
        $user = Auth::user();

        // ✅ Store payment in the database
        $payment = PaymentModel::create([
            'method' => $request->input('method'),
            'razorpay_payment_id' => $request->input('razorpay_payment_id'),
            'amount' => $request->input('amount'),
            'status' => $request->input('status'),
            'order_id' => $request->input('order_id'),
            'razorpay_order_id' => $request->input('razorpay_order_id'),
            'user' => $user->id ?? $request->input('user_id'), // Use Auth user or fallback to request
        ]);

        // Fetch the order along with its order items and the related products
        $order = OrderModel::with('items.product')->find($request->input('order_id'));
        if (!$order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        // Group order items by product_id and calculate total quantity per product
        $productStats = $order->items->groupBy('product_id')->map(function ($items) {
            return [
                'product_name' => $items->first()->product->name,
                'total_quantity' => $items->sum('quantity'),
            ];
        })->values();

        // Append the shipping address from the order to the response without storing it in the PaymentModel.
        $responseData = $payment->toArray();
        unset($responseData['id'], $responseData['created_at'], $responseData['updated_at']);
        $responseData['shipping_address'] = $order->shipping_address;

        return response()->json([
            'message' => 'Payment created successfully!',
            'data' => $responseData,
            'product_stats' => $productStats,
        ], 201);
    }
}
