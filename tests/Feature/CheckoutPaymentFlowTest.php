<?php

namespace Tests\Feature;

use App\Http\Controllers\RazorpayController;
use App\Models\CartModel;
use App\Models\CouponModel;
use App\Models\OrderModel;
use App\Models\OtpModel;
use App\Models\PaymentModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

/**
 * Razorpay stand-in: no network, records what checkout asked it to create.
 */
class FakeRazorpayController extends RazorpayController
{
    public static array $created = [];

    public function createOrder(Request $request)
    {
        $id = 'order_TEST' . (count(self::$created) + 1);
        self::$created[] = ['id' => $id, 'amount' => $request->amount];

        return response()->json([
            'success' => true,
            'order_id' => $id,
            'order' => ['id' => $id, 'amount' => $request->amount],
        ], 201);
    }
}

class CheckoutPaymentFlowTest extends TestCase
{
    private const SECRET = 'test_secret_value';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.razorpay.key' => 'rzp_test_KEY',
            'services.razorpay.secret' => self::SECRET,
        ]);

        $this->buildSchema();
        Mail::fake();

        FakeRazorpayController::$created = [];
        $this->app->bind(RazorpayController::class, fn () => new FakeRazorpayController());
    }

    private function buildSchema(): void
    {
        CheckoutSchema::build();
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function makeUser(array $attrs = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name' => 'User ' . $n,
            'email' => "user{$n}@example.com",
            'password' => 'secret-pass-123',
            'mobile' => '9' . str_pad((string) $n, 9, '0', STR_PAD_LEFT),
            'role' => 'customer',
        ], $attrs));
    }

    private function makeProduct(bool $ecommerce = true): ProductModel
    {
        return ProductModel::create(['name' => 'Fan', 'slug' => 'fan-' . uniqid(), 'is_ecommerce' => $ecommerce]);
    }

    private function makeVariant(ProductModel $product, float $regular, float $customerDiscount = 0, int $isCod = 0): ProductVariantModel
    {
        return ProductVariantModel::create([
            'product_id' => $product->id,
            'variant_value' => 'Matte Black',
            'regular_price' => $regular,
            'customer_discount' => $customerDiscount,
            'is_cod' => $isCod,
        ]);
    }

    private function addToCart(string $owner, ProductModel $product, ProductVariantModel $variant, int $qty = 1): void
    {
        CartModel::create([
            'user_id' => $owner,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => $qty,
        ]);
    }

    private function verifyMobile(string $mobile, ?string $updatedAt = null): void
    {
        $row = OtpModel::create(['mobile' => $mobile, 'otp' => '123456', 'status' => 'valid']);
        if ($updatedAt) {
            OtpModel::where('id', $row->id)->update(['updated_at' => $updatedAt]);
        }
    }

    private function orderRequest(array $extra = []): array
    {
        return array_merge([
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => 'Ramesh Kumar, 9876543210, Mumbai, Maharashtra, India, 400001, 12 MG Road',
            'shipping_charge' => 0,
        ], $extra);
    }

    private function orderPayload($response): array
    {
        return $response->json('data.data');
    }

    private function sign(string $razorpayOrderId, string $paymentId, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $razorpayOrderId . '|' . $paymentId, $secret);
    }

    // ── guest checkout: mobile verification → account ────────────────────

    public function test_guest_cannot_get_a_token_without_a_verified_otp(): void
    {
        $victim = $this->makeUser(['mobile' => '9000000001']);

        $res = $this->postJson('/api/make_user', [
            'name' => 'Attacker', 'email' => 'a@example.com', 'mobile' => '9000000001',
            'cart_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $res->assertStatus(403);
        $this->assertNull($res->json('token'));
        $this->assertSame(0, $victim->tokens()->count());
    }

    public function test_guest_with_verified_otp_gets_account_cart_moved_and_otp_is_single_use(): void
    {
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, 1000);
        $cartId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $this->addToCart($cartId, $product, $variant);
        $this->verifyMobile('9123456780');

        $payload = ['name' => 'Ramesh Kumar', 'email' => 'ramesh@example.com', 'mobile' => '9123456780', 'cart_id' => $cartId];

        $res = $this->postJson('/api/make_user', $payload);
        $res->assertStatus(201);
        $this->assertNotEmpty($res->json('token'));
        $this->assertNotEmpty($res->json('user.id'), 'session cookie is only set when the user id is returned');

        $user = User::where('mobile', '9123456780')->first();
        $this->assertSame(1, CartModel::where('user_id', (string) $user->id)->count());
        $this->assertSame(0, CartModel::where('user_id', $cartId)->count());

        // the OTP proof is consumed: replaying the request is rejected
        $this->postJson('/api/make_user', $payload)->assertStatus(403);
    }

    public function test_verified_otp_expires_after_thirty_minutes(): void
    {
        $this->verifyMobile('9123456781', now()->subMinutes(31)->toDateTimeString());

        $this->postJson('/api/make_user', [
            'name' => 'Ramesh Kumar', 'email' => 'r2@example.com', 'mobile' => '9123456781',
            'cart_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ])->assertStatus(403);
    }

    public function test_guest_cannot_take_over_an_account_by_using_its_email_with_their_own_mobile(): void
    {
        $victim = $this->makeUser(['email' => 'victim@example.com', 'mobile' => '9000000002']);
        $this->verifyMobile('9111111111'); // attacker's own, genuinely verified number

        $res = $this->postJson('/api/make_user', [
            'name' => 'Attacker', 'email' => 'victim@example.com', 'mobile' => '9111111111',
            'cart_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $res->assertStatus(422);
        $this->assertNull($res->json('token'));
        $this->assertSame(0, $victim->tokens()->count());
    }

    public function test_returning_customer_with_verified_mobile_is_signed_in_with_their_own_account(): void
    {
        $existing = $this->makeUser(['mobile' => '9000000003']);
        $this->verifyMobile('9000000003');

        $res = $this->postJson('/api/make_user', [
            'name' => 'Ignored', 'email' => 'ignored@example.com', 'mobile' => '9000000003',
            'cart_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $res->assertStatus(200);
        $this->assertSame($existing->id, $res->json('user.id'));
    }

    public function test_numeric_guest_cart_ids_are_rejected_everywhere(): void
    {
        $owner = $this->makeUser();
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, 1000);
        $this->addToCart((string) $owner->id, $product, $variant);
        $this->verifyMobile('9123456782');

        // cannot read someone's cart by posing as a guest with their user id
        $this->postJson('/api/cart/fetch', ['cart_id' => (string) $owner->id])->assertStatus(404);

        // cannot add into it either: a fresh random guest cart is created instead
        $res = $this->postJson('/api/cart/add', [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 5,
            'cart_id' => (string) $owner->id,
        ]);
        $res->assertStatus(201);
        $this->assertNotSame((string) $owner->id, (string) $res->json('data.user_id'));
        $this->assertSame(1, (int) CartModel::where('user_id', (string) $owner->id)->sum('quantity'));

        // and make_user will not migrate a registered user's cart into a new account
        $this->postJson('/api/make_user', [
            'name' => 'Attacker', 'email' => 'x@example.com', 'mobile' => '9123456782',
            'cart_id' => (string) $owner->id,
        ])->assertStatus(400);
    }

    public function test_wrong_otp_guess_does_not_revoke_an_already_verified_mobile(): void
    {
        $this->verifyMobile('9123456783');

        $this->postJson('/api/verify-otp', ['mobile' => '9123456783', 'otp' => '999999'])->assertStatus(400);
        $this->assertSame('valid', OtpModel::where('mobile', '9123456783')->value('status'));

        $this->postJson('/api/verify-otp', ['mobile' => '9123456783', 'otp' => '123456'])->assertStatus(200);
    }

    public function test_otp_verification_is_rate_limited_per_mobile(): void
    {
        OtpModel::create(['mobile' => '9123456784', 'otp' => '123456', 'status' => 'invalid']);

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/verify-otp', ['mobile' => '9123456784', 'otp' => '000000'])->assertStatus(400);
        }
        $this->postJson('/api/verify-otp', ['mobile' => '9123456784', 'otp' => '000000'])->assertStatus(429);
    }

    // ── placing the order ────────────────────────────────────────────────

    public function test_customer_cannot_create_an_order_that_is_already_paid(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, 10000, 50);
        $this->addToCart((string) $user->id, $product, $variant, 2);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/orders', $this->orderRequest([
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_mode' => 'COD',
        ]));

        // COD is not allowed for this variant, and paid/completed must never be honoured
        $res->assertStatus(422);
        $this->assertSame(0, OrderModel::count());

        $res = $this->postJson('/api/orders', $this->orderRequest(['status' => 'completed', 'payment_status' => 'paid']));
        $res->assertStatus(201);
        $order = OrderModel::first();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', PaymentModel::first()->status);
    }

    public function test_order_response_gives_checkout_the_exact_amount_key_and_ids(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, 15490, 50); // 7745 each
        $this->addToCart((string) $user->id, $product, $variant, 2);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/orders', $this->orderRequest());
        $res->assertStatus(201);
        $p = $this->orderPayload($res);

        $this->assertTrue($res->json('success'));
        $this->assertSame(1549000, $p['razorpay_amount']);       // 2 × ₹7,745.00 in paise
        $this->assertSame('rzp_test_KEY', $p['razorpay_key']);   // key comes from the server, not the browser
        $this->assertSame('order_TEST1', $p['razorpay_order_id']);
        $this->assertSame(1549000, FakeRazorpayController::$created[0]['amount']);
        $this->assertNotEmpty($p['order_id']);
    }

    public function test_amount_in_paise_is_rounded_not_truncated(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        // 5995.29 * 100 == 599528.9999999999 in floating point; a plain (int) cast loses a paisa
        $variant = $this->makeVariant($product, 5995.29);
        $this->addToCart((string) $user->id, $product, $variant);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderRequest())->assertStatus(201);
        $this->assertSame(599529, FakeRazorpayController::$created[0]['amount']);
    }

    public function test_cart_is_kept_until_paid_and_retrying_checkout_reuses_the_pending_order(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, 2000);
        $this->addToCart((string) $user->id, $product, $variant, 1);
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/orders', $this->orderRequest());
        $first->assertStatus(201);
        $this->assertSame(1, CartModel::where('user_id', (string) $user->id)->count(), 'cart survives an unpaid order');

        // payment window closed → customer clicks "Place order" again
        $second = $this->postJson('/api/orders', $this->orderRequest());
        $second->assertStatus(200);
        $this->assertSame($this->orderPayload($first)['order_id'], $this->orderPayload($second)['order_id']);
        $this->assertSame($this->orderPayload($first)['razorpay_order_id'], $this->orderPayload($second)['razorpay_order_id']);
        $this->assertSame(1, OrderModel::count());
        $this->assertCount(1, FakeRazorpayController::$created);

        // a changed cart is a different order
        CartModel::where('user_id', (string) $user->id)->update(['quantity' => 3]);
        $third = $this->postJson('/api/orders', $this->orderRequest());
        $third->assertStatus(201);
        $this->assertSame(2, OrderModel::count());
    }

    public function test_items_that_are_not_live_for_sale_cannot_be_ordered(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(false);
        $variant = $this->makeVariant($product, 2000);
        $this->addToCart((string) $user->id, $product, $variant);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderRequest())->assertStatus(422);
        $this->assertSame(0, OrderModel::count());
    }

    public function test_an_empty_cart_cannot_be_ordered(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/orders', $this->orderRequest())->assertStatus(400);
    }

    public function test_coupon_discounts_the_charge_and_uses_up_one_redemption(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, 10000);
        $this->addToCart((string) $user->id, $product, $variant);
        CouponModel::create([
            'coupon_code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10,
            'status' => 'active', 'count' => 1, 'validity' => now()->addDay()->toDateString(),
        ]);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/orders', $this->orderRequest(['coupon_code' => 'SAVE10']));
        $res->assertStatus(201);
        $this->assertSame(900000, $this->orderPayload($res)['razorpay_amount']);
        $this->assertSame(0, (int) CouponModel::where('coupon_code', 'SAVE10')->value('count'));

        // retrying the same checkout (payment window closed) re-opens that order: no error, no second use
        $retry = $this->postJson('/api/orders', $this->orderRequest(['coupon_code' => 'SAVE10']));
        $retry->assertStatus(200);
        $this->assertSame($this->orderPayload($res)['order_id'], $this->orderPayload($retry)['order_id']);
        $this->assertSame(1, OrderModel::count());

        // a different cart cannot redeem the used-up coupon
        CartModel::where('user_id', (string) $user->id)->update(['quantity' => 2]);
        $this->postJson('/api/orders', $this->orderRequest(['coupon_code' => 'SAVE10']))->assertStatus(422);
    }

    // ── delivery address ─────────────────────────────────────────────────

    private function addressBody(array $extra = []): array
    {
        return array_merge([
            'name' => 'Ramesh Kumar', 'contact_no' => '9876543210', 'address_line1' => '12 MG Road',
            'address_line2' => null, 'city' => 'Mumbai', 'state' => 'Maharashtra',
            'country' => 'India', 'postal_code' => '400001', 'is_default' => true,
        ], $extra);
    }

    public function test_customer_can_add_list_and_replace_the_default_address(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/address/register', $this->addressBody())->assertStatus(201);
        $this->postJson('/api/address/register', $this->addressBody(['city' => 'Pune']))->assertStatus(201);

        $list = $this->getJson('/api/address')->assertStatus(200);
        $this->assertSame(2, $list->json('count'));
        $defaults = collect($list->json('data'))->where('is_default', true);
        $this->assertCount(1, $defaults);
        $this->assertSame('Pune', $defaults->first()['city']);
    }

    public function test_address_requires_login_and_validates_required_fields(): void
    {
        $this->postJson('/api/address/register', $this->addressBody())->assertStatus(401);

        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/address/register', ['name' => 'Only a name'])->assertStatus(422);
    }

    public function test_customers_cannot_change_or_delete_each_others_addresses(): void
    {
        $owner = $this->makeUser();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/address/register', $this->addressBody())->json('data.id');

        Sanctum::actingAs($this->makeUser());
        $this->postJson("/api/address/update/{$id}", $this->addressBody(['city' => 'Hacked']))->assertStatus(404);
        $this->deleteJson("/api/address/{$id}")->assertStatus(404);
        $this->assertSame('Mumbai', \App\Models\AddressModel::find($id)->city);
    }

    // ── paying ───────────────────────────────────────────────────────────

    private function placeOrder(User $user, float $price = 2000, int $qty = 1): array
    {
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, $price);
        $this->addToCart((string) $user->id, $product, $variant, $qty);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/orders', $this->orderRequest());
        $res->assertStatus(201);

        return $this->orderPayload($res);
    }

    public function test_payment_with_a_valid_signature_marks_order_paid_and_empties_the_cart(): void
    {
        $user = $this->makeUser();
        $o = $this->placeOrder($user);

        $res = $this->postJson("/api/orders/{$o['order_id']}/verify-payment", [
            'razorpay_order_id' => $o['razorpay_order_id'],
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->sign($o['razorpay_order_id'], 'pay_ABC123'),
        ]);

        $res->assertStatus(200)->assertJsonPath('data.payment_status', 'paid');
        $order = OrderModel::find($o['order_id']);
        $this->assertSame('paid', $order->payment_status);
        $payment = PaymentModel::where('order_id', $order->id)->first();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('pay_ABC123', $payment->razorpay_payment_id);
        $this->assertSame(0, CartModel::where('user_id', (string) $user->id)->count());
        $this->assertNotNull($order->mail_sent_at, 'confirmation email is sent once payment is confirmed');

        // replaying the confirmation is harmless
        $this->postJson("/api/orders/{$o['order_id']}/verify-payment", [
            'razorpay_order_id' => $o['razorpay_order_id'],
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->sign($o['razorpay_order_id'], 'pay_ABC123'),
        ])->assertStatus(200);
        $this->assertSame(1, PaymentModel::where('order_id', $order->id)->count());
    }

    public function test_payment_with_a_bad_signature_is_rejected_and_order_stays_unpaid(): void
    {
        $user = $this->makeUser();
        $o = $this->placeOrder($user);

        foreach ([
            $this->sign($o['razorpay_order_id'], 'pay_ABC123', 'a-different-secret'),
            'not-a-signature',
        ] as $bad) {
            $this->postJson("/api/orders/{$o['order_id']}/verify-payment", [
                'razorpay_order_id' => $o['razorpay_order_id'],
                'razorpay_payment_id' => 'pay_ABC123',
                'razorpay_signature' => $bad,
            ])->assertStatus(422);
        }

        $this->assertSame('pending', OrderModel::find($o['order_id'])->payment_status);
        $this->assertSame(1, CartModel::where('user_id', (string) $user->id)->count());
    }

    public function test_signature_for_a_different_razorpay_order_is_rejected(): void
    {
        $user = $this->makeUser();
        $o = $this->placeOrder($user);

        $this->postJson("/api/orders/{$o['order_id']}/verify-payment", [
            'razorpay_order_id' => 'order_SOMEONE_ELSES',
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->sign('order_SOMEONE_ELSES', 'pay_ABC123'),
        ])->assertStatus(422);

        $this->assertSame('pending', OrderModel::find($o['order_id'])->payment_status);
    }

    public function test_another_customer_cannot_confirm_payment_for_my_order(): void
    {
        $owner = $this->makeUser();
        $o = $this->placeOrder($owner);

        Sanctum::actingAs($this->makeUser());
        $this->postJson("/api/orders/{$o['order_id']}/verify-payment", [
            'razorpay_order_id' => $o['razorpay_order_id'],
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->sign($o['razorpay_order_id'], 'pay_ABC123'),
        ])->assertStatus(404);
    }

    public function test_verification_refuses_to_run_without_a_configured_secret(): void
    {
        $user = $this->makeUser();
        $o = $this->placeOrder($user);
        config(['services.razorpay.secret' => '']);

        // with an empty secret anybody could compute a "valid" signature
        $this->postJson("/api/orders/{$o['order_id']}/verify-payment", [
            'razorpay_order_id' => $o['razorpay_order_id'],
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => hash_hmac('sha256', $o['razorpay_order_id'] . '|pay_ABC123', ''),
        ])->assertStatus(500);

        $this->assertSame('pending', OrderModel::find($o['order_id'])->payment_status);
    }

    public function test_customer_cannot_mark_their_own_order_paid_through_update_status(): void
    {
        $user = $this->makeUser();
        $o = $this->placeOrder($user);

        foreach ([
            ['payment_status' => 'paid'],
            ['status' => 'completed'],
            ['delivery_status' => 'completed'],
            ['status' => 'cancelled', 'payment_status' => 'paid'],
        ] as $body) {
            $this->postJson("/api/orders/{$o['order_id']}/update-status", $body)->assertStatus(403);
        }

        $order = OrderModel::find($o['order_id']);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->status);
    }

    public function test_customer_can_cancel_an_unpaid_order_but_not_a_paid_one(): void
    {
        $user = $this->makeUser();
        $o = $this->placeOrder($user);

        $this->postJson("/api/orders/{$o['order_id']}/update-status", ['status' => 'cancelled'])->assertStatus(200);
        $this->assertSame('cancelled', OrderModel::find($o['order_id'])->status);

        $paid = OrderModel::create([
            'user_id' => $user->id, 'total_amount' => 10, 'status' => 'pending', 'payment_status' => 'paid',
            'shipping_address' => 'x', 'razorpay_order_id' => 'order_PAID',
        ]);
        $this->postJson("/api/orders/{$paid->id}/update-status", ['status' => 'cancelled'])->assertStatus(422);
    }

    // ── deleting orders ──────────────────────────────────────────────────

    public function test_orders_can_only_be_deleted_by_their_owner_while_unpaid_or_by_an_admin(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $admin = $this->makeUser(['role' => 'admin']);

        $mk = fn (string $payment) => OrderModel::create([
            'user_id' => $owner->id, 'total_amount' => 10, 'status' => 'pending', 'payment_status' => $payment,
            'shipping_address' => 'x', 'razorpay_order_id' => 'order_' . uniqid(),
        ]);

        $unpaid = $mk('pending');
        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/orders/{$unpaid->id}")->assertStatus(404);
        $this->assertNotNull(OrderModel::find($unpaid->id));

        Sanctum::actingAs($owner);
        $paid = $mk('paid');
        $this->deleteJson("/api/orders/{$paid->id}")->assertStatus(403);
        $this->deleteJson("/api/orders/{$unpaid->id}")->assertStatus(200);
        $this->assertNull(OrderModel::find($unpaid->id));

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/orders/{$paid->id}")->assertStatus(200);
        $this->assertNull(OrderModel::find($paid->id));
    }
}
