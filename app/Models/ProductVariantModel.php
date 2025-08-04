<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantModel extends Model
{
    //
    protected $table = 't_product_variants';

    protected $fillable = [
        'product_id', 'photo_id', 'min_qty', 'is_cod', 'weight', 'description', 'variant_type',  'variant_value', 'discount_price', 'regular_price', 'selling_price', 'sales_price_vendor', 'customer_discount', 'dealer_discount', 'architect_discount', 'hsn', 'regular_tax', 'selling_tax', 'video_url', 'product_pdf'
    ];
    
    /**
     * Relation to Product table
     * Foreign Key: product_id
     * Primary Key: id (on Product table)
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id', 'id'); // product_id references id in Product table
    }
}
