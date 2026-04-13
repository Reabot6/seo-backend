<?php
namespace app\model;

use think\Model;

class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = [
        'domain',
        'site_name',
        'site_description',
        'copyright_text',
        'language',
        'keyword_1',
        'keyword_2',
        'keyword_3',
        'category',
        'status',
        'articles_count',
    ];

    // One site has many articles
    public function articles()
    {
        return $this->hasMany(Article::class, 'site_id');
    }

    // One site has many friendly links
    public function friendlyLinks()
    {
        return $this->hasMany(FriendlyLink::class, 'site_id');
    }

    // One site has many logs
    public function logs()
    {
        return $this->hasMany(Log::class, 'site_id');
    }

    // Get all keywords as clean array
    public function getKeywordsAttr($value, $data)
    {
        return array_filter([
            $data['keyword_1'] ?? null,
            $data['keyword_2'] ?? null,
            $data['keyword_3'] ?? null,
        ]);
    }

    // Scope — active sites only
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope — filter by language
    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language);
    }
}