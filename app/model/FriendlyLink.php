<?php
namespace app\model;

use think\Model;

class FriendlyLink extends Model
{
    protected $table = 'friendly_links';

    protected $fillable = [
        'site_id',
        'link_url',
        'link_text',
    ];

    // Friendly link belongs to a site
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    // Get all links for a specific site
    public static function getBySite($siteId)
    {
        return self::where('site_id', $siteId)->select();
    }

    // Add a friendly link
    public static function addLink($siteId, $url, $text)
    {
        return self::create([
            'site_id'   => $siteId,
            'link_url'  => $url,
            'link_text' => $text,
        ]);
    }

    // Remove a friendly link
    public static function removeLink($id)
    {
        return self::where('id', $id)->delete();
    }

    // Get links formatted for HTML output
    public static function getForHtml($siteId)
    {
        $links = self::getBySite($siteId);
        $html  = '';
        foreach ($links as $link) {
            $html .= '<a href="' . htmlspecialchars($link['link_url']) . '" rel="nofollow">'
                   . htmlspecialchars($link['link_text'])
                   . '</a>' . "\n";
        }
        return $html;
    }
}