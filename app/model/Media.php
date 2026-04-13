<?php
namespace app\model;

use think\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'filename',
        'filepath',
        'type',
        'size',
        'tags',
    ];

    // Media belongs to many articles
    public function articles()
    {
        return $this->belongsToMany(
            Article::class,
            'article_media',
            'media_id',
            'article_id'
        );
    }

    // Get tags as array
    public function getTagsArrayAttr($value, $data)
    {
        return $data['tags']
            ? array_map('trim', explode(',', $data['tags']))
            : [];
    }

    // Scope — images only
    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    // Scope — videos only
    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    // Find media by keyword tag
    public static function findByKeyword($keyword, $limit = 3)
    {
        return self::whereLike('tags', "%{$keyword}%")
            ->limit($limit)
            ->select();
    }
}