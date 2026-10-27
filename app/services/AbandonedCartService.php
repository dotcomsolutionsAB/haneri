<?php

namespace App\Services;

use App\Mail\AbandonedCartMail;
use App\Models\CartModel;
use App\Models\CartReminder;
use App\Models\CouponModel;
use App\Models\EmailLog;
use App\Models\OrderModel;
use App\Models\User;
use App\Http\Controllers\SettingController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AbandonedCartService
{
    public const COUPON_HOURS = 4;

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
