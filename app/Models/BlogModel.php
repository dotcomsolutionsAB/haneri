<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogModel extends Model
{
    protected $table = 't_blogs';

    protected $fillable = [
        'title',
        'sub_title',
        'slug',
        'content',
        'cover_image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function tags()
    {
        return $this->belongsToMany(BlogTagModel::class, 't_blog_tag_map', 'blog_id', 'blog_tag_id');
    }

    public function faqs()
    {
        return $this->hasMany(BlogFaqModel::class, 'blog_id', 'id')->orderBy('sort_order')->orderBy('id');
    }
}
