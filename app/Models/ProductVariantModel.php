<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantModel extends Model
{
    //
    protected $table = 't_product_variants';
    
    /**
     * Relation to Product table
     * Foreign Key: product_id
     * Primary Key: id (on Product table)
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id'); // product_id references id in Product table
    }
}
