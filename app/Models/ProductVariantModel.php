<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantModel extends Model
{
    //
    protected $table = 't_product_variants';

    protected $fillable = [
        'product_id', 'sku', 'photo_id', 'banner_id', 'min_qty', 'is_cod', 'weight', 'length', 'width', 'height', 'description', 'variant_type',  'variant_value', 'light_option', 'discount_price', 'regular_price', 'customer_discount', 'dealer_discount', 'architect_discount', 'hsn', 'regular_tax', 'selling_tax', 'video_url', 'product_pdf', '3d_file', '3d_placeholder'
    ];

    public function optionLabel(): string
    {
        $color = trim((string) $this->variant_value);
        $light = match ($this->light_option) {
            'with' => 'With light',
            'without' => 'Without light',
            default => null,
        };

        return $light ? $color.' ('.$light.')' : $color;
    }
    
    /**
     * Relation to Product table
     * Foreign Key: product_id
     * Primary Key: id (on Product table)
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id', 'id'); // product_id references id in Product table
    }

    /* optional: all quotation items that picked this variant */
    public function quotationItems()
    {
        return $this->hasMany(QuotationItemModel::class, 'variant_id');
    }
}
