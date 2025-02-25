<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{
    //
    protected $table = 't_orders';

    protected $fillable = ['user_id', 'total_amount', 'status', 'payment_status', 'shipping_address', 'razorpay_order_id'];

     /**
     * Relation to the User table
     * Foreign Key: user_id (on Order table)
     * Primary Key: id (on User table)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id'); // user_id references id in User table
    }

     /**
     * Relation to the OrderItem table
     * Foreign Key: order_id (on OrderItem table)
     * Primary Key: id (on Order table)
     */
    public function items()
    {
        return $this->hasMany(OrderItemModel::class, 'order_id', 'id'); // order_id references id in Order table
    }
}
