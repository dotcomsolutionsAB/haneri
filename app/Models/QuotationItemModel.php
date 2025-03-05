<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItemModel extends Model
{
    //
    protected $table = 't_quotation_items';

    protected $fillable = ['quotation_id', 'product_id', 'variant_id', 'quantity', 'price'];
}
