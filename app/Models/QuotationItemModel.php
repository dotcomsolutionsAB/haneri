<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItemModel extends Model
{
    //
    protected $table = 't_quotation_items';

    protected $fillable = ['quotation_id', 'product_id', 'variant_id', 'quantity', 'price'];

     /**
     * Each quotation item belongs to one product.
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}
