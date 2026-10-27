<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartMail;
use App\Models\CartModel;
use App\Models\CartReminder;
use App\Models\CartRestoreLink;
use App\Models\CouponModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\SettingModel;
use App\Models\User;
use App\services\AbandonedCartService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CheckoutSchema;
use Tests\TestCase;

class AbandonedCartReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CheckoutSchema::build();
        Mail::fake();
    }

    public function test_one_hour_idle_sends_the_first_email_without_a_coupon(): void
    {
        [$user, $variant] = $this->catalog();
        $this->cart($user->id, $variant->id, now()->subHours(2));

        Artisan::call('carts:remind-abandoned');

        Mail::assertSent(AbandonedCartMail::class, function (AbandonedCartMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->stage === 1 && $mail->couponCode === null;
        });
        $this->assertSame(0, CouponModel::count());
        $this->assertNotNull(CartReminder::where('user_id', (string) $user->id)->value('first_sent_at'));
        $this->assertNull(CartReminder::where('user_id', (string) $user->id)->value('second_sent_at'));
    }

    public function test_twenty_four_hours_without_a_first_email_sends_only_the_coupon_email(): void
    {
        [$user, $variant] = $this->catalog();
        $this->cart($user->id, $variant->id, now()->subHours(25));

        Artisan::call('carts:remind-abandoned');

        Mail::assertSent(AbandonedCartMail::class, 1);
        Mail::assertSent(AbandonedCartMail::class, function (AbandonedCartMail $mail) {
            return $mail->stage === 2 && $mail->discountPercent === 10 && $mail->couponCode !== null;
        });

        $coupon = CouponModel::first();
        $this->assertNotNull($coupon);
        $this->assertSame($user->id, (int) $coupon->user_id);
        $this->assertSame(1, (int) $coupon->count);
        $this->assertSame(10.0, (float) $coupon->discount_value);
        $this->assertTrue($coupon->expires_at->between(now()->addHours(3), now()->addHours(4)->addMinute()));
        $this->assertNull(CouponModel::where('coupon_code', $coupon->coupon_code)->redeemable(999)->first());
        $this->assertNotNull(CouponModel::where('coupon_code', $coupon->coupon_code)->redeemable($user->id)->first());

        $reminder = CartReminder::where('user_id', (string) $user->id)->first();
        $this->assertNotNull($reminder->first_sent_at);
        $this->assertNotNull($reminder->second_sent_at);
        $this->assertSame($coupon->coupon_code, $reminder->coupon_code);
    }

    public function test_second_email_uses_the_saved_discount_percent(): void
    {
        SettingModel::create([
            'key' => 'abandoned_cart',
            'value' => json_encode(['discount_percent' => 15]),
        ]);
        [$user, $variant] = $this->catalog();
        $this->cart($user->id, $variant->id, now()->subHours(30));

        Artisan::call('carts:remind-abandoned');

        $this->assertSame(15.0, (float) CouponModel::first()->discount_value);
    }

    public function test_guest_carts_are_not_emailed(): void
    {
        [, $variant] = $this->catalog();
        $this->cart('guest-cart-token', $variant->id, now()->subHours(5));

        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingSent();
    }

    public function test_an_order_after_the_cart_change_stops_the_email(): void
    {
        [$user, $variant] = $this->catalog();
        $this->cart($user->id, $variant->id, now()->subHours(3));
        OrderModel::create([
            'user_id' => $user->id,
            'total_amount' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => 'Noida',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingSent();
    }

    public function test_editing_the_cart_starts_the_sequence_again(): void
    {
        [$user, $variant] = $this->catalog();
        $cart = $this->cart($user->id, $variant->id, now()->subHours(3));
        Artisan::call('carts:remind-abandoned');
        Mail::assertSent(AbandonedCartMail::class, 1);

        CartModel::where('id', $cart->id)->update([
            'updated_at' => now()->subMinutes(90),
            'created_at' => now()->subMinutes(90),
        ]);

        Artisan::call('carts:remind-abandoned');

        Mail::assertSent(AbandonedCartMail::class, 2);
        Mail::assertSent(AbandonedCartMail::class, fn (AbandonedCartMail $mail) => $mail->stage === 1);
        $this->assertSame(0, CouponModel::count());
    }

    public function test_an_expired_coupon_cannot_be_redeemed(): void
    {
        $coupon = CouponModel::create([
            'coupon_code' => 'CART-EXPIRED',
            'user_id' => 1,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'count' => 1,
            'validity' => now()->toDateString(),
            'expires_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        $this->assertNull(CouponModel::where('coupon_code', $coupon->coupon_code)->redeemable(1)->first());
    }

    public function test_admin_list_includes_guests_and_labels_them(): void
    {
        [$user, $variant] = $this->catalog();
        $this->cart($user->id, $variant->id, now()->subHours(2));
        $this->cart('guest-cart-token', $variant->id, now()->subHours(2));

        $rows = app(AbandonedCartService::class)->rows();
        $statuses = collect($rows)->pluck('status', 'user_id');

        $this->assertSame('not_sent', $statuses[(string) $user->id]);
        $this->assertSame('guest', $statuses['guest-cart-token']);
    }

    public function test_restore_link_puts_back_removed_items_without_changing_ones_still_there(): void
    {
        [$user, $variant] = $this->catalog();
        $removed = $this->cart($user->id, $variant->id, now()->subHours(2));
        CartModel::where('id', $removed->id)->update([
            'quantity' => 2,
            'updated_at' => now()->subHours(2),
        ]);

        $keptProduct = ProductModel::create([
            'name' => 'Aero Fan',
            'slug' => 'aero',
            'is_active' => true,
        ]);
        $keptVariant = ProductVariantModel::create([
            'product_id' => $keptProduct->id,
            'variant_value' => 'White',
            'regular_price' => 5000,
            'customer_discount' => 0,
        ]);
        $kept = $this->cart($user->id, $keptVariant->id, now()->subHours(2));

        Artisan::call('carts:remind-abandoned');

        $token = null;
        Mail::assertSent(AbandonedCartMail::class, function (AbandonedCartMail $mail) use (&$token) {
            $query = [];
            parse_str((string) parse_url($mail->cartUrl, PHP_URL_QUERY), $query);
            $token = $query['restore'] ?? null;

            return is_string($token) && $token !== '';
        });

        CartModel::where('id', $removed->id)->delete();

        Sanctum::actingAs($user);
        $this->postJson('/api/cart/restore', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.restored', 1)
            ->assertJsonPath('data.already_in_cart', 1);

        $this->assertSame(2, (int) CartModel::where('variant_id', $variant->id)->value('quantity'));
        $this->assertSame(1, (int) CartModel::where('id', $kept->id)->value('quantity'));
    }

    public function test_restore_link_does_not_fill_someone_elses_cart(): void
    {
        [$user, $variant] = $this->catalog();
        $this->cart($user->id, $variant->id, now()->subHours(2));
        Artisan::call('carts:remind-abandoned');
        $token = CartRestoreLink::first()->token;
        CartModel::query()->delete();

        $other = User::create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'password' => 'secret-pass-123',
            'role' => 'customer',
        ]);

        Sanctum::actingAs($other);
        $this->postJson('/api/cart/restore', ['token' => $token])->assertForbidden();
        $this->assertSame(0, CartModel::count());

        Sanctum::actingAs($user);
        $this->postJson('/api/cart/restore', ['token' => $token])->assertOk();
        $this->assertSame(1, CartModel::where('user_id', (string) $user->id)->count());
        $this->assertSame(0, CartModel::where('user_id', (string) $other->id)->count());
    }

    public function test_restore_skips_a_product_that_is_no_longer_for_sale(): void
    {
        [$user, $variant] = $this->catalog();
        $cart = $this->cart($user->id, $variant->id, now()->subHours(2));
        Artisan::call('carts:remind-abandoned');
        $token = CartRestoreLink::first()->token;

        CartModel::where('id', $cart->id)->delete();
        ProductModel::where('id', $variant->product_id)->update(['is_ecommerce' => false]);

        Sanctum::actingAs($user);
        $this->postJson('/api/cart/restore', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.restored', 0)
            ->assertJsonPath('data.unavailable', 1);

        $this->assertSame(0, CartModel::count());
    }

    private function catalog(): array
    {
        $user = User::create([
            'name' => 'Ramesh Kumar',
            'email' => 'ramesh@example.com',
            'password' => 'secret-pass-123',
            'role' => 'customer',
        ]);
        $product = ProductModel::create([
            'name' => 'FengShui Ceiling Fan',
            'slug' => 'fengshui',
            'is_active' => true,
        ]);
        $variant = ProductVariantModel::create([
            'product_id' => $product->id,
            'variant_value' => 'Espresso Walnut',
            'regular_price' => 10000,
            'customer_discount' => 0,
        ]);

        return [$user, $variant];
    }

    private function cart(int|string $userId, int $variantId, $updatedAt): CartModel
    {
        $productId = ProductVariantModel::find($variantId)->product_id;
        $cart = CartModel::create([
            'user_id' => (string) $userId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => 1,
        ]);
        CartModel::where('id', $cart->id)->update([
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        return $cart->fresh();
    }
}
