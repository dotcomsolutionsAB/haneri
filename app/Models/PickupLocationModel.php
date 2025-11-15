<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickupLocationModel extends Model
{
    protected $table = 't_pickup_location';

    protected $fillable = [
        'name',
        'code',
        'courier_pickup_name',
        'courier_pickup_code',
        'contact_person',
        'phone',
        'alternate_phone',
        'email',
        'address_line1',
        'address_line2',
        'landmark',
        'city',
        'district',
        'state',
        'pin',
        'country',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'pin'        => 'integer',
    ];

    // If later you add relation to orders:
    public function orders()
    {
        return $this->hasMany(OrderModel::class, 'pickup_location_id', 'id');
    }
}
