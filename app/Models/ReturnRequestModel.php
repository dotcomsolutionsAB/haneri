<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequestModel extends Model
{
    use HasFactory;

    protected $table = 't_return_requests'; // Specify the table name

    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'reason',
        'status',
    ];

    // Define relationships if necessary

    /**
     * Get the order associated with the return request.
     */
    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id', 'id');
    }

    /**
     * Get the user who made the return request.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}