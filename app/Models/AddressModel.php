<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressModel extends Model
{
    //
    protected $table = 't_addresses';

    protected $fillable = [
        'user_id', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country', 'is_default'
    ];

    /**
     * Relation to the User model.
     * A User can have multiple addresses.
     * 
     * Foreign Key: user_id (on UserAddress table)
     * Primary Key: id (on User table)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
