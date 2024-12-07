<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFeatureModel extends Model
{
    //
    protected $table = 't_product_features';

    protected $fillable = [
        'product_id', 'feature_name', 'feature_value', 'is_filterable'
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
