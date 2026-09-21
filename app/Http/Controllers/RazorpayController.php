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
use App\Models\EmailLog;

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
        $this->markOrderPaid(
            (int) $orderId,
            (string) $razorpayOrderId,
            (string) $razorpayPaymentId,
            $paymentMethodLower
        );

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
     * Razorpay server-to-server webhook.
     * Dashboard URL: POST /api/razorpay/webhook
     * Events: payment.captured, payment.failed (optional: order.paid)
     */
    public function handleWebhook(Request $request)
    {
        $webhookSecret = (string) config('services.razorpay.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $payload = $request->getContent();

        if ($webhookSecret === '') {
            Log::error('Razorpay webhook rejected: RAZORPAY_WEBHOOK_SECRET is not set');
            return response()->json(['success' => false, 'message' => 'Webhook not configured'], 500);
        }

        if ($signature === '' || $payload === '') {
            return response()->json(['success' => false, 'message' => 'Missing signature or body'], 400);
        }

        $expected = hash_hmac('sha256', $payload, $webhookSecret);
        if (! hash_equals($expected, $signature)) {
            Log::warning('Razorpay webhook signature mismatch');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $event = (string) ($data['event'] ?? '');
        Log::info('Razorpay webhook received', [
            'event' => $event,
            'id' => $data['id'] ?? null,
        ]);

        try {
            if ($event === 'payment.captured' || $event === 'order.paid') {
                $this->handleWebhookPaymentCaptured($data, $event);
            } elseif ($event === 'payment.failed') {
                $this->handleWebhookPaymentFailed($data);
            }
        } catch (\Throwable $e) {
            Log::error('Razorpay webhook processing failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }

        return response()->json(['success' => true], 200);
    }

    private function handleWebhookPaymentCaptured(array $data, string $event): void
    {
        $paymentEntity = $data['payload']['payment']['entity'] ?? null;
        if (! is_array($paymentEntity) && $event === 'order.paid') {
            // order.paid may only include order; try first payment under order if present
            $orderEntity = $data['payload']['order']['entity'] ?? [];
            $razorpayOrderId = (string) ($orderEntity['id'] ?? '');
            if ($razorpayOrderId === '') {
                Log::warning('Razorpay order.paid missing order id');
                return;
            }
            $this->markOrderPaidByRazorpayOrderId($razorpayOrderId, null, null);
            return;
        }

        if (! is_array($paymentEntity)) {
            Log::warning('Razorpay payment.captured missing payment entity');
            return;
        }

        $razorpayPaymentId = (string) ($paymentEntity['id'] ?? '');
        $razorpayOrderId = (string) ($paymentEntity['order_id'] ?? '');
        $method = strtolower((string) ($paymentEntity['method'] ?? 'razorpay'));

        if ($razorpayOrderId === '' || $razorpayPaymentId === '') {
            Log::warning('Razorpay payment.captured missing ids', [
                'payment_id' => $razorpayPaymentId,
                'order_id' => $razorpayOrderId,
            ]);
            return;
        }

        $this->markOrderPaidByRazorpayOrderId($razorpayOrderId, $razorpayPaymentId, $method);
    }

    private function handleWebhookPaymentFailed(array $data): void
    {
        $paymentEntity = $data['payload']['payment']['entity'] ?? null;
        if (! is_array($paymentEntity)) {
            return;
        }

        $razorpayPaymentId = (string) ($paymentEntity['id'] ?? '');
        $razorpayOrderId = (string) ($paymentEntity['order_id'] ?? '');
        if ($razorpayOrderId === '') {
            return;
        }

        DB::transaction(function () use ($razorpayOrderId, $razorpayPaymentId) {
            $order = OrderModel::where('razorpay_order_id', $razorpayOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                Log::warning('Razorpay payment.failed: order not found', [
                    'razorpay_order_id' => $razorpayOrderId,
                ]);
                return;
            }

            // Do not downgrade an already-paid order.
            if ($order->payment_status === 'paid') {
                return;
            }

            $order->payment_status = 'failed';
            $order->save();

            $payment = PaymentModel::where('order_id', $order->id)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->first();

            if ($payment) {
                $payment->status = 'failed';
                if ($razorpayPaymentId !== '') {
                    $payment->razorpay_payment_id = $razorpayPaymentId;
                }
                $payment->save();
            }
        });
    }

    private function markOrderPaidByRazorpayOrderId(
        string $razorpayOrderId,
        ?string $razorpayPaymentId,
        ?string $method
    ): void {
        $order = OrderModel::where('razorpay_order_id', $razorpayOrderId)->first();
        if (! $order) {
            Log::warning('Razorpay webhook: local order not found', [
                'razorpay_order_id' => $razorpayOrderId,
            ]);
            return;
        }

        $this->markOrderPaid(
            (int) $order->id,
            $razorpayOrderId,
            $razorpayPaymentId,
            $method
        );
    }

    /**
     * Idempotent: mark order + payment as paid and send confirmation email once.
     * Used by browser callback, webhook, and customer resync.
     */
    public function applyCapturedPayment(
        int $orderId,
        string $razorpayOrderId,
        ?string $razorpayPaymentId,
        ?string $paymentMethodLower
    ): void {
        $this->markOrderPaid($orderId, $razorpayOrderId, $razorpayPaymentId, $paymentMethodLower);
    }

    /**
     * Idempotent: mark order + payment as paid and send confirmation email once.
     */
    private function markOrderPaid(
        int $orderId,
        string $razorpayOrderId,
        ?string $razorpayPaymentId,
        ?string $paymentMethodLower
    ): void {
        DB::transaction(function () use ($orderId, $razorpayOrderId, $razorpayPaymentId, $paymentMethodLower) {
            $order = OrderModel::where('id', $orderId)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                Log::error('Order not found while marking paid', [
                    'order_id' => $orderId,
                    'razorpay_order_id' => $razorpayOrderId,
                ]);
                return;
            }

            $alreadyPaid = $order->payment_status === 'paid';

            if (! $alreadyPaid) {
                $order->payment_status = 'paid';
                // Keep order status as-is: DB enum is pending|completed|cancelled|refunded
                $order->save();
            }

            $payment = PaymentModel::where('order_id', $order->id)
                ->where('razorpay_order_id', $razorpayOrderId)
                ->first();

            $method = $paymentMethodLower ?: 'razorpay';

            if ($payment) {
                $payment->status = 'paid';
                if ($razorpayPaymentId) {
                    $payment->razorpay_payment_id = $razorpayPaymentId;
                }
                if ($paymentMethodLower) {
                    $payment->method = $method;
                }
                $payment->save();
            } elseif ($razorpayPaymentId) {
                PaymentModel::create([
                    'method' => $method,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'amount' => $order->total_amount,
                    'status' => 'paid',
                    'order_id' => $order->id,
                    'razorpay_order_id' => $razorpayOrderId,
                    'user' => $order->user_id,
                ]);
            }

            if ($order->mail_sent_at) {
                return;
            }

            $orderUser = User::find($order->user_id);
            if (! $orderUser) {
                return;
            }

            $items = OrderItemModel::with(['product:id,name', 'variant:id,variant_type,variant_value'])
                ->where('order_id', $order->id)
                ->get()
                ->map(function ($it) {
                    $vType = optional($it->variant)->variant_type;
                    $vValue = optional($it->variant)->variant_value;
                    $variantLabel = $vValue ? ($vType ? ($vType.': '.$vValue) : $vValue) : null;

                    return [
                        'name' => optional($it->product)->name ?? ('Product #'.$it->product_id),
                        'variant' => $variantLabel,
                        'qty' => (int) $it->quantity,
                        'price' => (float) $it->price,
                        'total' => (float) $it->price * (int) $it->quantity,
                    ];
                })
                ->toArray();

            try {
                Mail::to($orderUser->email)->send(new OrderPlacedMail($orderUser, $order, $items));
                $order->mail_sent_at = now();
                $order->save();
                EmailLog::record($orderUser->email, OrderPlacedMail::class, 'sent', [
                    'recipient_user_id' => $orderUser->id,
                    'subject' => 'Order Confirmed • #'.$order->id.' • '.config('app.name'),
                ]);
            } catch (\Throwable $e) {
                Log::warning('OrderPlacedMail failed for order '.$orderId.': '.$e->getMessage());
                EmailLog::record($orderUser->email, OrderPlacedMail::class, 'failed', [
                    'recipient_user_id' => $orderUser->id,
                    'error_message' => $e->getMessage(),
                ]);
            }
        });
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
