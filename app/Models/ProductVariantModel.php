<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantModel extends Model
{
    //
    protected $table = 't_product_variants';

    protected $fillable = [
        'product_id', 'variant_type', 'variant_value', 'price'
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
