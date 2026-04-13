<?php
namespace app\controller;

use think\facade\Db;

class DashboardController
{
    // GET /api/dashboard/stats
    public function stats()
    {
        $today = date('Y-m-d');

        $totalSites    = Db::table('sites')->count();
        $totalArticles = Db::table('articles')->count();

        $articlesToday = Db::table('articles')
            ->whereRaw("DATE(created_at) = '{$today}'")
            ->count();

        $indexedToday = Db::table('articles')
            ->whereRaw("DATE(created_at) = '{$today}'")
            ->where(function($q) {
                $q->where('google_indexed', 1)
                  ->whereOr('bing_indexed', 1);
            })
            ->count();

        $published = Db::table('articles')->where('status', 'published')->count();
        $pending   = Db::table('articles')->where('status', 'pending')->count();
        $failed    = Db::table('articles')->where('status', 'failed')->count();

        return json([
            'status' => 'success',
            'data'   => [
                'total_sites'    => $totalSites,
                'total_articles' => $totalArticles,
                'articles_today' => $articlesToday,
                'indexed_today'  => $indexedToday,
                'published'      => $published,
                'pending'        => $pending,
                'failed'         => $failed,
            ],
        ]);
    }

    // GET /api/dashboard/recent-articles
    public function recentArticles()
    {
        $articles = Db::table('articles')
            ->field('id, site_id, title, keyword, status, originality_score, created_at')
            ->order('created_at', 'desc')
            ->limit(10)
            ->select()->toArray();

        $siteIds = array_unique(array_column($articles, 'site_id'));

        if (!empty($siteIds)) {
            $sites = Db::table('sites')->whereIn('id', $siteIds)->column('domain', 'id');
        } else {
            $sites = [];
        }

        foreach ($articles as &$article) {
            $article['site_domain'] = $sites[$article['site_id']] ?? '';
        }

        return json(['status' => 'success', 'data' => $articles]);
    }

    // GET /api/dashboard/logs
   public function logs()
{
    $page  = request()->get('page', 1);
    $limit = request()->get('limit', 10);

    $total = Db::table('logs')->count();
    $logs  = Db::table('logs')
        ->order('created_at', 'desc')
        ->page($page, $limit)
        ->select()->toArray();

    return json([
        'status' => 'success',
        'data'   => $logs,
        'total'  => $total,
    ]);
}
    // GET /api/dashboard/chart
    public function chart()
    {
        $days      = [];
        $published = [];
        $indexed   = [];

        for ($i = 6; $i >= 0; $i--) {
            $date   = date('Y-m-d', time() - ($i * 86400));
            $days[] = date('M d', time() - ($i * 86400));

            $published[] = Db::table('articles')
                ->where('status', 'published')
                ->whereRaw("DATE(created_at) = '{$date}'")
                ->count();

            $indexed[] = Db::table('articles')
                ->where('google_indexed', 1)
                ->whereRaw("DATE(created_at) = '{$date}'")
                ->count();
        }

        return json([
            'status' => 'success',
            'data'   => [
                'days'      => $days,
                'published' => $published,
                'indexed'   => $indexed,
            ],
        ]);
    }
}