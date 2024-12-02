<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandModel extends Model
{
    //
    protected $table = 't_brands';

    protected $fillable = ['name', 'logo', 'custom_sort', 'description'];

    /**
     * Relation to the Product model.
     * A Brand can have many Products.
     * 
     * Foreign Key: brand_id (on Product table)
     * Primary Key: id (on Brand table)
     */
    public function products()
    {
        return $this->hasMany(ProductModel::class, 'brand_id', 'id');
    }
}
