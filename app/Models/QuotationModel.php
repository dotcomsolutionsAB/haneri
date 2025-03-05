<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationModel extends Model
{
    //
    protected $table = 't_quotations';

    protected $fillable = ['user_id', 'total_amount', 'status', 'payment_status', 'shipping_address', 'razorpay_order_id'];
}
