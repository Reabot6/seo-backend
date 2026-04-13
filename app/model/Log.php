<?php
namespace app\model;

use think\Model;

class Log extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'site_id',
        'article_id',
        'event',
        'message',
        'status',
    ];

    // Log belongs to a site
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    // Log belongs to an article
    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    // Scope — errors only
    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    // Scope — today's logs
    public function scopeToday($query)
    {
        return $query->whereDay('created_at', date('d'));
    }

    // Helper to create a log entry quickly
    public static function record($event, $message, $status = 'success', $siteId = null, $articleId = null)
    {
        return self::create([
            'site_id'    => $siteId,
            'article_id' => $articleId,
            'event'      => $event,
            'message'    => $message,
            'status'     => $status,
        ]);
    }
}