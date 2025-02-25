<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\Order;
use Exception;

class RazorpayController extends Controller
{
    //
    protected $razorpay;

    public function __construct()
    {
        // $apiKey = env('RAZORPAY_KEY');
        // $apiSecret = env('RAZORPAY_SECRET');

        // if (!$apiKey || !$apiSecret) {
        //     throw new \Exception('Razorpay API credentials are missing.');
        // }
        
        $this->razorpay = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    /**
     * Create an order in Razorpay
     */
    public function createOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|in:INR',
            'receipt' => 'nullable|string',
        ]);

        try {
            $orderData = [
                'amount' => $request->amount * 100, // Amount in paise
                'currency' => $request->currency,
                'receipt' => $request->receipt ?? 'order_receipt_' . time(),
                'payment_capture' => 1, // Auto capture payment
            ];

            // ✅ Create Order in Razorpay
            $order = $this->razorpay->order->create($orderData);

            // ✅ Extract Order ID
            $orderId = $order['id'];

            // ✅ Convert Razorpay Order Object to Array Properly
            $orderArray = $order->toArray();

            // ✅ Log Response
            \Log::info('Razorpay Order Created:', ['order' => $orderArray]);

            return response()->json([
                'success' => true,
                'message' => 'Razorpay order created successfully.',
                'order_id' => $orderId, // Return Order ID separately
                'order' => $orderArray,
            ], 201);
        } catch (Exception $e) {
            \Log::error('Razorpay Order Error:', ['error' => $e->getMessage()]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error creating Razorpay order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify Razorpay Payment Signature
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {
            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            $this->razorpay->utility->verifyPaymentSignature($attributes);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 400);
        }
    }
}
