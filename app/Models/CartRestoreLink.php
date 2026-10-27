<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartRestoreLink extends Model
{
    protected $table = 't_cart_restore_links';

    protected $fillable = [
        'token',
        'user_id',
        'items',
        'expires_at',
    ];

    protected $casts = [
        'items' => 'array',
        'expires_at' => 'datetime',
    ];
}
