<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponModel extends Model
{
    //
    protected $table = 't_coupons';

    protected $fillable = ['code', 'discount_type', 'discount_value', 'expiration_date', 'usage_limit', 'used_count'];
}
