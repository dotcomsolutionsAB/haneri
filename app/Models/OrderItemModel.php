<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemModel extends Model
{
    //
    protected $table = 't_order_items';

    protected $fillable = ['order_id', 'product_id', 'variant_id', 'quantity', 'price'];

    /**
     * Relation to the Order model.
     * Foreign Key: order_id (on OrderItem table)
     * Primary Key: id (on Order table)
     */
    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id', 'id'); // order_id references id in Order table
    }

    /**
     * Relation to the Product model.
     * Foreign Key: product_id (on OrderItem table)
     * Primary Key: id (on Product table)
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id', 'id'); // product_id references id in Product table
    }

    /**
     * Relation to the ProductVariant model.
     * Foreign Key: variant_id (on OrderItem table)
     * Primary Key: id (on ProductVariant table)
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariantModel::class, 'variant_id', 'id'); // variant_id references id in ProductVariant table
    }
}
