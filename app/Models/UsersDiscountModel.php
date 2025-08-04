<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersDiscountModel extends Model
{
    //
    protected $table = 't_users_discount';

    protected $fillable = ['user_id', 'product_variant_id', 'category_id', 'discount'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

}
