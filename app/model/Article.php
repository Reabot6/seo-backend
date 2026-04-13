<?php
namespace app\model;

use think\Model;

class Article extends Model
{
    protected $table = 'articles';

    protected $fillable = [
        'site_id',
        'title',
        'description',
        'body',
        'keyword',
        'category',
        'originality_score',
        'status',
        'google_indexed',
        'bing_indexed',
    ];

    // Article belongs to a site
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    // Article has many media
    public function media()
    {
        return $this->belongsToMany(
            Media::class,
            'article_media',
            'article_id',
            'media_id'
        );
    }

    // Scope — published only
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    // Scope — failed only
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Scope — pending only
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}