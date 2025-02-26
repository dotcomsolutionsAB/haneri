<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentModel extends Model
{
    //
    protected $table = 't_payment_records';

    protected $fillable = [
        'method', 'razorpay_payment_id', 'amount', 'status', 'order_id', 'razorpay_order_id', 'user'
    ];
}
