<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogFaqModel extends Model
{
    protected $table = 't_blog_faqs';

    protected $fillable = [
        'blog_id',
        'question',
        'answer',
        'sort_order',
    ];

    public function blog()
    {
        return $this->belongsTo(BlogModel::class, 'blog_id', 'id');
    }
}
