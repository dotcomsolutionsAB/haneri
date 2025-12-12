<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderShipment extends Model
{
    protected $table = 't_order_shipments';

    protected $fillable = [
        'order_id',
        'user_id',
        'courier',
        'status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'shipping_pin',
        'shipping_city',
        'shipping_state',
        'payment_mode',
        'total_amount',
        'cod_amount',
        'quantity',
        'weight',
        'products_description',
        'pickup_location_id',
        'pickup_name',
        'pickup_address',
        'pickup_pin',
        'pickup_city',
        'pickup_state',
        'pickup_phone',
        'awb_no',
        'courier_reference',
        'request_payload',
        'response_payload',
        'error_message',
        'booked_at',
        // New columns you added
        'seller_name',
        'seller_address',
        'seller_invoice',
        'shipment_length',
        'shipment_width',
        'shipment_height',
        'shipping_mode',
        'address_type',
        'return_pin',
        'return_city',
        'return_state',
        'return_phone',
        'return_address',
        'return_country',
        
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'booked_at'        => 'datetime',
        'delivered_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function pickupLocation()
    {
        return $this->belongsTo(PickupLocationModel::class, 'pickup_location_id');
    }
}
