<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationTermModel extends Model
{
    //
    protected $table = 't_quotation_items';

    protected $fillable = ['order_id', 'product_id', 'variant_id', 'quantity', 'price'];
}
