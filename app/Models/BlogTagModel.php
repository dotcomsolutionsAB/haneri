<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTagModel extends Model
{
    protected $table = 't_blog_tags';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function blogs()
    {
        return $this->belongsToMany(BlogModel::class, 't_blog_tag_map', 'blog_tag_id', 'blog_id');
    }
}
