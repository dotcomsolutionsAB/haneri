<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponModel extends Model
{
    //
    protected $table = 't_coupons';

    protected $fillable = [
        'coupon_code',
        'user_id',
        'discount_type',
        'discount_value',
        'count',
        'validity',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'validity' => 'date',
        'expires_at' => 'datetime',
    ];

    /**
     * Coupons a customer can still redeem. expires_at is optional so date-only
     * coupons keep working; abandoned-cart codes set it to a 4-hour window.
     */
    public function scopeRedeemable($query, int|string $userId)
    {
        return $query->where('status', 'active')
            ->whereDate('validity', '>=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
