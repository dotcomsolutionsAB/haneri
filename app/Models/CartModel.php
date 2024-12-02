<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartModel extends Model
{
    //
    protected $table = 't_carts';

    protected $fillable = ['user_id', 'product_id', 'variant_id', 'quantity'];

    /**
     * Relation to the User model.
     * Foreign Key: user_id (on CartItem table)
     * Primary Key: id (on User table)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id'); // user_id references id in User table
    }

    /**
     * Relation to the Product model.
     * Foreign Key: product_id (on CartItem table)
     * Primary Key: id (on Product table)
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id', 'id'); // product_id references id in Product table
    }

     /**
     * Relation to the ProductVariant model.
     * Foreign Key: variant_id (on CartItem table)
     * Primary Key: id (on ProductVariant table)
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariantModel::class, 'variant_id', 'id'); // variant_id references id in ProductVariant table
    }
}
