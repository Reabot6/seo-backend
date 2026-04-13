<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class ArticlesController
{
    // GET /api/articles
    public function index(Request $request)
    {
        $page    = $request->get('page', 1);
        $limit   = $request->get('limit', 20);
        $search  = $request->get('search', '');
        $status  = $request->get('status', '');
        $siteId  = $request->get('site_id', '');

        $query = Db::table('articles');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereLike('title', "%{$search}%")
                  ->whereOrLike('keyword', "%{$search}%");
            });
        }

        if ($status) $query->where('status', $status);
        if ($siteId) $query->where('site_id', $siteId);

        $total    = $query->count();
        $articles = $query->page($page, $limit)
                          ->order('created_at', 'desc')
                          ->select()->toArray();

        // Attach site domain to each article
        $siteIds  = array_unique(array_column($articles, 'site_id'));
        $sites    = Db::table('sites')->whereIn('id', $siteIds)->column('domain', 'id');

        foreach ($articles as &$article) {
            $article['site_domain'] = $sites[$article['site_id']] ?? '';
            // Don't return full body in list
            unset($article['body']);
        }

        return json([
            'status' => 'success',
            'data'   => $articles,
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
        ]);
    }

    // GET /api/articles/:id
public function show(Request $request, $id = null)
{
    $id = $id ?: $request->get('id');
    
    if (!$id) {
        return json(['status' => 'error', 'message' => 'No ID'], 400);
    }

    $rows = Db::query(
        "SELECT a.*, s.domain as site_domain FROM articles a LEFT JOIN sites s ON a.site_id = s.id WHERE a.id = ?",
        [(int)$id]
    );

    if (empty($rows)) {
        return json(['status' => 'error', 'message' => 'Not found'], 404);
    }

    return json(['status' => 'success', 'data' => $rows[0]]);
}    // DELETE /api/articles/:id
    public function destroy($id)
    {
        $article = Db::table('articles')->where('id', $id)->find();
        if (!$article) {
            return json(['status' => 'error', 'message' => 'Article not found'], 404);
        }

        Db::table('articles')->where('id', $id)->delete();
        Db::table('article_media')->where('article_id', $id)->delete();

        return json(['status' => 'success', 'message' => 'Article deleted successfully']);
    }
    public function debug($id)
{
    $raw = Db::query("SELECT id, LENGTH(body) as len, SUBSTRING(body, 1, 50) as preview FROM articles WHERE id = ?", [$id]);
    return json(['raw' => $raw]);
}
public function destroyById(Request $request)
{
    $id = $request->get('id');
    
    if (!$id) {
        return json(['status' => 'error', 'message' => 'No ID'], 400);
    }

    $article = Db::table('articles')->where('id', (int)$id)->find();
    if (!$article) {
        return json(['status' => 'error', 'message' => 'Article not found'], 404);
    }

    Db::table('articles')->where('id', (int)$id)->delete();
    Db::table('article_media')->where('article_id', (int)$id)->delete();

    return json(['status' => 'success', 'message' => 'Article deleted']);
}

}