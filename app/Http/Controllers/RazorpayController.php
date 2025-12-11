<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\OrderModel;
use App\Models\PaymentModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


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
        // From query string (sent by your frontend in callback_url)
        $orderId         = $request->query('order_id');          // local order ID
        $shippingAddress = $request->query('shipping_address');

        // From Razorpay POST body
        $razorpayPaymentId = $request->input('razorpay_payment_id');
        $razorpayOrderId   = $request->input('razorpay_order_id');
        $razorpaySignature = $request->input('razorpay_signature');

        Log::info('Razorpay callback received', [
            'order_id'            => $orderId,
            'shipping_address'    => $shippingAddress,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_order_id'   => $razorpayOrderId,
            'razorpay_signature'  => $razorpaySignature,
        ]);

        // 🔴 Basic validation – if anything critical is missing, mark as failed
        if (!$orderId || !$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
            Log::warning('Razorpay callback missing params');

            // Try to mark order/payment as failed, but don't crash if not found
            DB::transaction(function () use ($orderId, $razorpayOrderId, $razorpayPaymentId) {
                if ($orderId) {
                    $order = OrderModel::where('id', $orderId)
                        ->where('razorpay_order_id', $razorpayOrderId)
                        ->first();

                    if ($order) {
                        $order->payment_status = 'failed';   // ENUM: pending, paid, failed, refunded
                        $order->save();

                        $payment = PaymentModel::where('order_id', $order->id)
                            ->where('razorpay_order_id', $razorpayOrderId)
                            ->first();

                        if ($payment) {
                            $payment->status = 'failed';
                            $payment->razorpay_payment_id = $razorpayPaymentId;
                            $payment->save();
                        }
                    }
                }
            });

            return redirect()->away(
                'https://haneri.com/order-complete.php'
                . '?status=failed'
                . '&order_id=' . urlencode($orderId ?? 0)
            );
        }

        // ✅ Verify Razorpay signature
        $expectedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            config('services.razorpay.secret')   // same secret used in constructor
        );

        if (!hash_equals($expectedSignature, $razorpaySignature)) {
            Log::error('Razorpay signature mismatch', [
                'expected' => $expectedSignature,
                'got'      => $razorpaySignature,
            ]);

            // Signature mismatch ⇒ treat as failed
            DB::transaction(function () use ($orderId, $razorpayOrderId, $razorpayPaymentId) {
                $order = OrderModel::where('id', $orderId)
                    ->where('razorpay_order_id', $razorpayOrderId)
                    ->first();

                if ($order) {
                    $order->payment_status = 'failed';
                    $order->save();

                    $payment = PaymentModel::where('order_id', $order->id)
                        ->where('razorpay_order_id', $razorpayOrderId)
                        ->first();

                    if ($payment) {
                        $payment->status = 'failed';
                        $payment->razorpay_payment_id = $razorpayPaymentId;
                        $payment->save();
                    }
                }
            });

            return redirect()->away(
                'https://haneri.com/order-complete.php'
                . '?status=failed'
                . '&order_id=' . urlencode($orderId)
            );
        }

        // ✅ Signature OK ⇒ mark payment as PAID in DB
        DB::transaction(function () use ($orderId, $razorpayOrderId, $razorpayPaymentId) {
            $order = OrderModel::where('id', $orderId)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->first();

            if (!$order) {
                Log::error('Order not found during Razorpay callback', [
                    'order_id'          => $orderId,
                    'razorpay_order_id' => $razorpayOrderId,
                ]);
                return;
            }

            // 1️⃣ Update orders.payment_status
            $order->payment_status = 'paid';   // ENUM: pending, paid, failed, refunded
            $order->save();

            // 2️⃣ Update / create payment record in t_payment_records
            $payment = PaymentModel::where('order_id', $order->id)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->first();

            if ($payment) {
                // update existing pending record
                $payment->status              = 'paid';      // ENUM mirror
                $payment->razorpay_payment_id = $razorpayPaymentId;
                $payment->save();
            } else {
                // safety fallback – create one if it doesn't exist
                PaymentModel::create([
                    'method'             => 'upi',
                    'razorpay_payment_id'=> $razorpayPaymentId,
                    'amount'             => $order->total_amount,
                    'status'             => 'paid',
                    'order_id'           => $order->id,
                    'razorpay_order_id'  => $razorpayOrderId,
                    'user'               => $order->user_id, // column name is `user` in your model
                ]);
            }
        });

        // 🔁 Redirect user to frontend success page
        $redirectUrl = 'https://haneri.com/order-complete.php'
            . '?status=success'
            . '&order_id=' . urlencode($orderId)
            . '&payment_id=' . urlencode($razorpayPaymentId)
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
