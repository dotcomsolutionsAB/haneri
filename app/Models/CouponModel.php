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
        'validity'
    ];

    protected $casts = [
        'discount_value' => 'float',
        'validity'       => 'date',
    ];
}
