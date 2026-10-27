<?php

namespace App\services;

use App\Mail\AbandonedCartMail;
use App\Models\CartModel;
use App\Models\CartReminder;
use App\Models\CartRestoreLink;
use App\Models\CouponModel;
use App\Models\EmailLog;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\User;
use App\Http\Controllers\SettingController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AbandonedCartService
{
    public const COUPON_HOURS = 4;

    public const RESTORE_DAYS = 7;

    /**
     * @return array{records: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(string $search, int $limit, int $offset): array
    {
        $rows = $this->rows();
        $needle = mb_strtolower(trim($search));
        if ($needle !== '') {
            $rows = array_values(array_filter($rows, function (array $row) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['customer_name'],
                    $row['email'],
                    $row['is_guest'] ? 'guest' : '',
                    $row['coupon_code'],
                    $row['status'],
                ])));

                return str_contains($haystack, $needle);
            }));
        }

        return [
            'records' => array_slice($rows, $offset, $limit),
            'total' => count($rows),
        ];
    }

    public function sendDueReminders(): int
    {
        $sent = 0;
        foreach ($this->groups() as $group) {
            if ($this->remind($group->user_id, Carbon::parse($group->last_activity))) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->groups() as $group) {
            $rows[] = $this->present($group->user_id, Carbon::parse($group->last_activity));
        }

        usort($rows, fn ($a, $b) => strcmp($b['last_activity'], $a['last_activity']));

        return $rows;
    }

    private function groups()
    {
        return CartModel::query()
            ->select('user_id')
            ->selectRaw('MAX(updated_at) as last_activity')
            ->groupBy('user_id')
            ->havingRaw('MAX(updated_at) <= ?', [now()->subHour()->toDateTimeString()])
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(string $userId, Carbon $lastActivity): array
    {
        $lines = $this->lines($userId);
        $isGuest = !$this->isRegisteredId($userId);
        $user = $isGuest ? null : User::find((int) $userId);
        $ordered = $this->hasOrderSince($userId, $lastActivity);
        $reminder = CartReminder::where('user_id', $userId)->first();
        $sequenceCurrent = $reminder
            && $reminder->anchor_at
            && $lastActivity->lte($reminder->anchor_at);

        if ($isGuest) {
            $status = 'guest';
        } elseif ($ordered) {
            $status = 'ordered';
        } elseif ($sequenceCurrent && $reminder->second_sent_at) {
            $status = 'second_sent';
        } elseif ($sequenceCurrent && $reminder->first_sent_at) {
            $status = 'first_sent';
        } else {
            $status = 'not_sent';
        }

        $quantity = 0;
        $value = 0.0;
        $items = [];
        foreach ($lines as $line) {
            $price = $this->sellingPrice($line);
            $qty = (int) $line->quantity;
            $quantity += $qty;
            $value += $price * $qty;
            $items[] = [
                'name' => $line->product->name ?? 'Product',
                'variant' => $line->variant->variant_value ?? null,
                'quantity' => $qty,
                'price' => $price,
            ];
        }

        return [
            'user_id' => $userId,
            'customer_name' => $isGuest ? 'Guest' : ($user->name ?? 'Customer'),
            'email' => $user->email ?? null,
            'is_guest' => $isGuest,
            'items' => $items,
            'quantity' => $quantity,
            'value' => round($value, 2),
            'last_activity' => $lastActivity->toIso8601String(),
            'status' => $status,
            'coupon_code' => $sequenceCurrent ? $reminder->coupon_code : null,
            'first_sent_at' => $sequenceCurrent && $reminder->first_sent_at
                ? $reminder->first_sent_at->toIso8601String()
                : null,
            'second_sent_at' => $sequenceCurrent && $reminder->second_sent_at
                ? $reminder->second_sent_at->toIso8601String()
                : null,
        ];
    }

    private function remind(string $userId, Carbon $lastActivity): bool
    {
        if (!$this->isRegisteredId($userId) || $this->hasOrderSince($userId, $lastActivity)) {
            return false;
        }

        $user = User::find((int) $userId);
        if (!$user || trim((string) $user->email) === '') {
            return false;
        }

        $reminder = CartReminder::firstOrNew(['user_id' => $userId]);
        $anchor = $reminder->anchor_at;
        if (!$reminder->exists || !$anchor || $lastActivity->gt($anchor)) {
            $reminder->anchor_at = $lastActivity;
            $reminder->first_sent_at = null;
            $reminder->second_sent_at = null;
            $reminder->coupon_code = null;
            $reminder->save();
        }

        $pastDay = $lastActivity->lte(now()->subHours(24));
        $pastHour = $lastActivity->lte(now()->subHour());

        if ($pastDay && !$reminder->second_sent_at) {
            $markFirst = $reminder->first_sent_at === null;
            if (!$this->send($user, $userId, 2)) {
                return false;
            }
            $reminder->coupon_code = $this->lastCouponCode;
            $reminder->second_sent_at = now();
            if ($markFirst) {
                $reminder->first_sent_at = now();
            }
            $reminder->save();

            return true;
        }

        if ($pastHour && !$reminder->first_sent_at) {
            if (!$this->send($user, $userId, 1)) {
                return false;
            }
            $reminder->first_sent_at = now();
            $reminder->save();

            return true;
        }

        return false;
    }

    private ?string $lastCouponCode = null;

    private function send(User $user, string $userId, int $stage): bool
    {
        $this->lastCouponCode = null;
        $lines = $this->lines($userId);
        $items = [];
        foreach ($lines as $line) {
            $price = $this->sellingPrice($line);
            $qty = (int) $line->quantity;
            $items[] = [
                'name' => $line->product->name ?? 'Product',
                'variant' => $line->variant->variant_value ?? null,
                'qty' => $qty,
                'price' => $price,
                'total' => round($price * $qty, 2),
            ];
        }

        $coupon = null;
        $percent = SettingController::abandonedCartDiscount();
        if ($stage === 2) {
            $coupon = $this->createCoupon($user, $percent);
            $this->lastCouponCode = $coupon->coupon_code;
        }

        $mailable = new AbandonedCartMail(
            $user,
            $items,
            $stage,
            $coupon?->coupon_code,
            $stage === 2 ? $percent : null,
            $coupon?->expires_at,
            $this->restoreUrl($user, $lines),
        );

        try {
            Mail::to($user->email)->send($mailable);
            EmailLog::record($user->email, AbandonedCartMail::class, 'sent', [
                'subject' => $mailable->envelope()->subject,
                'recipient_user_id' => $user->id,
                'metadata' => [
                    'stage' => $stage,
                    'coupon_code' => $coupon?->coupon_code,
                ],
            ]);

            return true;
        } catch (\Throwable $e) {
            if ($coupon) {
                $coupon->update(['status' => 'inactive']);
                $this->lastCouponCode = null;
            }
            EmailLog::record($user->email, AbandonedCartMail::class, 'failed', [
                'subject' => $mailable->envelope()->subject,
                'recipient_user_id' => $user->id,
                'error_message' => $e->getMessage(),
                'metadata' => ['stage' => $stage],
            ]);

            return false;
        }
    }

    /**
     * Put emailed lines back when they are missing. A line that is still in the
     * cart keeps its current quantity.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function restore(User $user, string $token): array
    {
        if ($user->role === 'admin') {
            return $this->restoreResponse(403, false, 'Admin accounts cannot add products to the cart.');
        }

        $link = CartRestoreLink::where('token', $token)->first();
        if (!$link || $link->expires_at->lte(now())) {
            return $this->restoreResponse(410, false, 'This cart link has expired.');
        }
        if ((int) $link->user_id !== (int) $user->id) {
            return $this->restoreResponse(403, false, 'Sign in with the account that received this email.');
        }

        $restored = 0;
        $already = 0;
        $unavailable = 0;

        foreach ($link->items ?? [] as $item) {
            if (!is_array($item)) {
                $unavailable++;
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== null
                ? (int) $item['variant_id']
                : null;
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $product = ProductModel::find($productId);
            if (!$product || !$product->is_ecommerce || !$product->is_active) {
                $unavailable++;
                continue;
            }

            if ($variantId) {
                $variant = ProductVariantModel::query()
                    ->where('id', $variantId)
                    ->where('product_id', $productId)
                    ->first();
                if (!$variant) {
                    $unavailable++;
                    continue;
                }
            }

            $existing = CartModel::query()
                ->where('user_id', (string) $user->id)
                ->where('product_id', $productId)
                ->where(function ($query) use ($variantId) {
                    if ($variantId) {
                        $query->where('variant_id', $variantId);
                    } else {
                        $query->whereNull('variant_id');
                    }
                })
                ->first();

            if ($existing) {
                $already++;
                continue;
            }

            CartModel::create([
                'user_id' => (string) $user->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
            $restored++;
        }

        if ($restored > 0) {
            $message = 'We put the items from your email back in your cart.';
        } elseif ($unavailable > 0 && $already === 0) {
            $message = 'Those products are no longer available.';
        } else {
            $message = 'Your cart already has these items.';
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => $message,
                'data' => [
                    'restored' => $restored,
                    'already_in_cart' => $already,
                    'unavailable' => $unavailable,
                ],
            ],
        ];
    }

    private function restoreUrl(User $user, $lines): string
    {
        $snapshot = [];
        foreach ($lines as $line) {
            $snapshot[] = [
                'product_id' => (int) $line->product_id,
                'variant_id' => $line->variant_id ? (int) $line->variant_id : null,
                'quantity' => max(1, (int) $line->quantity),
            ];
        }

        $link = CartRestoreLink::create([
            'token' => Str::random(40),
            'user_id' => $user->id,
            'items' => $snapshot,
            'expires_at' => now()->addDays(self::RESTORE_DAYS),
        ]);

        $frontend = rtrim((string) env('APP_FRONTEND_URL', 'https://haneri.com'), '/');

        return $frontend . '/cart?restore=' . $link->token;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function restoreResponse(int $status, bool $success, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => $success,
                'message' => $message,
            ],
        ];
    }

    private function createCoupon(User $user, int $percent): CouponModel
    {
        $expires = now()->addHours(self::COUPON_HOURS);

        return CouponModel::create([
            'coupon_code' => $this->uniqueCode(),
            'user_id' => $user->id,
            'discount_type' => 'percentage',
            'discount_value' => $percent,
            'count' => 1,
            'validity' => $expires->toDateString(),
            'expires_at' => $expires,
            'status' => 'active',
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'CART-' . strtoupper(Str::random(8));
        } while (CouponModel::where('coupon_code', $code)->exists());

        return $code;
    }

    private function lines(string $userId)
    {
        return CartModel::with([
            'product:id,name',
            'variant:id,variant_value,regular_price,customer_discount',
        ])->where('user_id', $userId)->get();
    }

    private function sellingPrice(CartModel $line): float
    {
        $variant = $line->variant;
        if (!$variant) {
            return 0.0;
        }
        $regular = (float) $variant->regular_price;
        $discount = (float) ($variant->customer_discount ?? 0);

        return (float) round($regular - ($regular * ($discount / 100)), 0);
    }

    private function hasOrderSince(string $userId, Carbon $lastActivity): bool
    {
        if (!$this->isRegisteredId($userId)) {
            return false;
        }

        return OrderModel::query()
            ->where('user_id', (int) $userId)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('created_at', '>=', $lastActivity)
            ->exists();
    }

    private function isRegisteredId(string $userId): bool
    {
        return ctype_digit($userId) && (int) $userId > 0;
    }
}
