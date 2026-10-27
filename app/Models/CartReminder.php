<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartReminder extends Model
{
    protected $table = 't_cart_reminders';

    protected $fillable = [
        'user_id',
        'anchor_at',
        'first_sent_at',
        'second_sent_at',
        'coupon_code',
    ];

    protected $casts = [
        'anchor_at' => 'datetime',
        'first_sent_at' => 'datetime',
        'second_sent_at' => 'datetime',
    ];
}
