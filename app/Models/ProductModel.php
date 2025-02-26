<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    //
    protected $table = 't_products';

    protected $fillable = [
        'name', 'brand_id', 'category_id', 'slug', 'description', 'is_active'
    ];

     /**
     * Relation to the Upload table for photo
     * Foreign Key: photo_id
     * Primary Key: id (on Upload table)
     */
    public function photo()
    {
        return $this->belongsTo(UploadModel::class, 'photo_id', 'id'); // photo_id references id in Uploads table
    }

    /**
     * Relation to ProductVariant table
     * Foreign Key: product_id (on ProductVariant table)
     * Primary Key: id (on Product table)
     */
    public function variants()
    {
        return $this->hasMany(ProductVariantModel::class, 'product_id', 'id'); // product_id references id in Product table
    }

    /**
     * Relation to ProductFeature table
     * Foreign Key: product_id (on ProductFeature table)
     * Primary Key: id (on Product table)
     */
    public function features()
    {
        return $this->hasMany(ProductFeatureModel::class, 'product_id', 'id');
    }

     /**
     * Relation to Brand table
     * Foreign Key: brand_id
     * Primary Key: id (on Brand table)
     */
    public function brand()
    {
        return $this->belongsTo(BrandModel::class, 'brand_id', 'id');  // brand_id references id in Brand table
    }

       /**
     * Relation to Category table
     * Foreign Key: category_id
     * Primary Key: id (on Category table)
     */
    public function category()
    {
        return $this->belongsTo(CategoryModel::class, 'category_id', 'id'); // category_id references id in Category table
    }

    public function upload()
    {
        return $this->belongsTo(CategoryModel::class, 'photo_id', 'id'); // photo_id references id in Category table
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItemModel::class, 'product_id', 'id');
    }
}
