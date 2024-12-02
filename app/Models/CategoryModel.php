<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryModel extends Model
{
    //
    protected $table = 't_categories';

    protected $fillable = ['name', 'parent_id', 'photo', 'custom_sort', 'description'];
}
