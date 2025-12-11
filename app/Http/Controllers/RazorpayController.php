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
        // amount is expected in paise here (integer)
        $request->validate([
            'amount'   => 'required|integer|min:100', // min ₹1.00
            'currency' => 'required|string|in:INR',
            'receipt'  => 'nullable|string',
        ]);

        try {
            $orderData = [
                'amount'          => $request->amount, // already in paise
                'currency'        => $request->currency,
                'receipt'         => $request->receipt ?? ('order_receipt_' . time()),
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
                'success'   => true,
                'message'   => 'Razorpay order created successfully.',
                'order_id'  => $orderId,
                'order'     => $orderArray,
            ], 201);
        } catch (Exception $e) {
            \Log::error('Razorpay Order Error:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating Razorpay order: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    public function handleCallback(Request $request)
    {
        // From query string
        $orderId = $request->query('order_id');          // 43
        $shippingAddress = $request->query('shipping_address');

        // From Razorpay POST body
        $razorpayPaymentId = $request->input('razorpay_payment_id');
        $razorpayOrderId   = $request->input('razorpay_order_id');
        $razorpaySignature = $request->input('razorpay_signature');

        // TODO:
        // 1. Verify signature
        // 2. Mark payment as paid in DB
        // 3. Update order status, etc.

        // Finally, redirect user to your frontend success page:
        $redirectUrl = 'https://haneri.com/order-complete.php'
            . '?status=success'
            . '&order_id=' . urlencode($orderId)
            . '&payment_id=' . urlencode($razorpayPaymentId)
            . '&amount=' . urlencode($request->input('amount') / 100 ?? 0)
            . '&shipping_address=' . urlencode($shippingAddress);

        return redirect()->away($redirectUrl);
    }

    // public function createOrder(Request $request)
    // {
    //     $request->validate([
    //         'amount' => 'required|numeric|min:1',
    //         'currency' => 'required|string|in:INR',
    //         'receipt' => 'nullable|string',
    //     ]);

    //     try {
    //         $orderData = [
    //             'amount' => $request->amount * 100, // Amount in paise
    //             'currency' => $request->currency,
    //             'receipt' => $request->receipt ?? 'order_receipt_' . time(),
    //             'payment_capture' => 1, // Auto capture payment
    //         ];

    //         // ✅ Create Order in Razorpay
    //         $order = $this->razorpay->order->create($orderData);

    //         // ✅ Extract Order ID
    //         $orderId = $order['id'];

    //         // ✅ Convert Razorpay Order Object to Array Properly
    //         $orderArray = $order->toArray();

    //         // ✅ Log Response
    //         \Log::info('Razorpay Order Created:', ['order' => $orderArray]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Razorpay order created successfully.',
    //             'order_id' => $orderId, // Return Order ID separately
    //             'order' => $orderArray,
    //         ], 201);
    //     } catch (Exception $e) {
    //         \Log::error('Razorpay Order Error:', ['error' => $e->getMessage()]);
    
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error creating Razorpay order: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

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

    public function fetchPaymentStatus($paymentId)
    {
        try {
            // $paymentDetails = $this->razorpay->fetchPaymentDetails($paymentId);
            $paymentDetails = $this->razorpay->payment->fetch($paymentId);
            
            $paymentArray = $paymentDetails->toArray();

            return response()->json($paymentArray);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function fetchOrderStatus($orderId)
    {
        try {
            $orderDetails = $this->razorpay->order->fetch($orderId);

            // ✅ Convert to an array for logging
            // $orderArray = json_decode(json_encode($orderDetails), true);
            $orderArray = $orderDetails->toArray();

            // ✅ Log the raw response for debugging
            \Log::info('Fetched Order Details: ', $orderArray);

            return response()->json($orderArray);
        } catch (\Exception $e) {

            \Log::error('Error fetching order status: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
