<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\OrderModel;
use App\Models\PaymentModel;
use App\Models\User;
use App\Models\OrderItemModel;
use App\Mail\OrderPlacedMail;
use Illuminate\Support\Facades\Mail;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailLog;
use App\Mail\OrderPlacedMail;


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
                'https://haneri.com/account/order-complete.php'
                . '?status=failed'
                . '&method=' . urlencode('Razorpay')   // ✅ add this
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
                'https://haneri.com/account/order-complete.php'
                . '?status=failed'
                . '&method=' . urlencode('Razorpay')   // ✅ add this
                . '&order_id=' . urlencode($orderId)
            );
        }
        // ✅ Fetch actual payment method from Razorpay (upi/card/netbanking/wallet etc.)
        $paymentMethod = 'Razorpay';
        try {
            $rzpPayment = $this->razorpay->payment->fetch($razorpayPaymentId);

            if (!empty($rzpPayment['method'])) {
                $paymentMethod = (string) $rzpPayment['method']; // e.g. upi, card, netbanking
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to fetch Razorpay payment method: ' . $e->getMessage());
        }

        $methodLabel = strtoupper($paymentMethod);
        $map = [
            'upi'        => 'UPI',
            'card'       => 'Card',
            'netbanking' => 'Net Banking',
            'wallet'     => 'Wallet',
            'emi'        => 'EMI',
            'paylater'   => 'Pay Later',
        ];
        $paymentMethodLower = strtolower($paymentMethod);
        if (isset($map[$paymentMethodLower])) {
            $methodLabel = $map[$paymentMethodLower];
        }


        // ✅ Signature OK ⇒ mark payment as PAID in DB + send email once
        DB::transaction(function () use ($orderId, $razorpayOrderId, $razorpayPaymentId, $paymentMethodLower) {

            $order = OrderModel::where('id', $orderId)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                Log::error('Order not found during Razorpay callback', [
                    'order_id'          => $orderId,
                    'razorpay_order_id' => $razorpayOrderId,
                ]);
                return;
            }

            // 1️⃣ Update orders.payment_status
            $order->payment_status = 'paid';
            $order->save();

            // 2️⃣ Update / create payment record
            $payment = PaymentModel::where('order_id', $order->id)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->first();

            if ($payment) {
                $payment->status              = 'paid';
                $payment->razorpay_payment_id = $razorpayPaymentId;
                $payment->method              = $paymentMethodLower ?: $payment->method; // ✅ add this
                $payment->save();
            } else {
                PaymentModel::create([
                    'method'              => $paymentMethodLower ?: 'razorpay',
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'amount'              => $order->total_amount,
                    'status'              => 'paid',
                    'order_id'            => $order->id,
                    'razorpay_order_id'   => $razorpayOrderId,
                    'user'                => $order->user_id,
                ]);
            }

            // ✅ Send email ONCE
            if ($order->mail_sent_at) {
                return; // already sent earlier
            }

            $orderUser = User::find($order->user_id);
            if (!$orderUser) return;

            $items = OrderItemModel::with(['product:id,name', 'variant:id,variant_type,variant_value'])
                ->where('order_id', $order->id)
                ->get()
                ->map(function($it) {
                    $vType  = optional($it->variant)->variant_type;
                    $vValue = optional($it->variant)->variant_value;
                    $variantLabel = $vValue ? ($vType ? ($vType . ': ' . $vValue) : $vValue) : null;

                    return [
                        'name'    => optional($it->product)->name ?? ('Product #'.$it->product_id),
                        'variant' => $variantLabel,
                        'qty'     => (int) $it->quantity,
                        'price'   => (float) $it->price,
                        'total'   => (float) $it->price * (int)$it->quantity,
                    ];
                })
                ->toArray();

            try {
                Mail::to($orderUser->email)->send(new OrderPlacedMail($orderUser, $order, $items));
                $order->mail_sent_at = now();
                $order->save();
                EmailLog::record($orderUser->email, OrderPlacedMail::class, 'sent', [
                    'recipient_user_id' => $orderUser->id,
                    'subject'            => 'Order Confirmed • #' . $order->id . ' • ' . config('app.name'),
                ]);
            } catch (\Throwable $e) {
                Log::warning('OrderPlacedMail failed in callback for order '.$orderId.': '.$e->getMessage());
                EmailLog::record($orderUser->email, OrderPlacedMail::class, 'failed', [
                    'recipient_user_id' => $orderUser->id,
                    'error_message'     => $e->getMessage(),
                ]);
            }
        });

        // 🔁 Redirect user to frontend success page
        $redirectUrl = 'https://haneri.com/account/order-complete.php'
            . '?status=success'
            . '&method=' . urlencode($methodLabel)   // ✅ ADD THIS
            . '&order_id=' . urlencode($orderId)
            . '&payment_id=' . urlencode($razorpayPaymentId)
            . '&shipping_address=' . urlencode($shippingAddress);

        return redirect()->away($redirectUrl);
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
