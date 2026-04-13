<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class SitesController
{
    // GET /api/sites
    public function index(Request $request)
    {
        $page     = $request->get('page', 1);
        $limit    = $request->get('limit', 20);
        $search   = $request->get('search', '');
        $status   = $request->get('status', '');

        $query = Db::table('sites');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereLike('domain', "%{$search}%")
                  ->whereOrLike('keyword_1', "%{$search}%")
                  ->whereOrLike('keyword_2', "%{$search}%")
                  ->whereOrLike('keyword_3', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $sites = $query->page($page, $limit)
                       ->order('created_at', 'desc')
                       ->select()->toArray();

        return json([
            'status' => 'success',
            'data'   => $sites,
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
        ]);
    }
    // GET /api/sites/:id/links
public function getLinks($id)
{
    $links = Db::table('friendly_links')->where('site_id', $id)->select()->toArray();
    return json(['status' => 'success', 'data' => $links]);
}

// POST /api/sites/:id/links
public function addLink(Request $request, $id)
{
    $url  = $request->post('link_url');
    $text = $request->post('link_text');

    if (!$url || !$text) {
        return json(['status' => 'error', 'message' => 'URL and text required'], 400);
    }

    $linkId = Db::table('friendly_links')->insertGetId([
        'site_id'    => $id,
        'link_url'   => $url,
        'link_text'  => $text,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    return json(['status' => 'success', 'id' => $linkId]);
}

// DELETE /api/sites/link/:id
public function deleteLink($id)
{
    Db::table('friendly_links')->where('id', $id)->delete();
    return json(['status' => 'success', 'message' => 'Link deleted']);
}

    // POST /api/sites
    public function store(Request $request)
    {
        $data = $request->post();

        if (empty($data['domain'])) {
            return json(['status' => 'error', 'message' => 'Domain is required'], 400);
        }

        $id = Db::table('sites')->insertGetId([
            'domain'           => $data['domain'],
            'site_name'        => $data['site_name']        ?? '',
            'site_description' => $data['site_description'] ?? '',
            'copyright_text'   => $data['copyright_text']   ?? '',
            'language'         => $data['language']         ?? 'en',
            'keyword_1'        => $data['keyword_1']        ?? '',
            'keyword_2'        => $data['keyword_2']        ?? '',
            'keyword_3'        => $data['keyword_3']        ?? '',
            'category'         => $data['category']         ?? '',
            'publish_type'     => $data['publish_type']     ?? 'wordpress',
            'publish_endpoint' => $data['publish_endpoint'] ?? '',
            'publish_api_key'  => $data['publish_api_key']  ?? '',
            'status'           => 'active',
            'articles_count'   => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return json([
            'status'  => 'success',
            'message' => 'Site created successfully',
            'id'      => $id,
        ]);
    }

    // PUT /api/sites/:id
    public function update(Request $request, $id)
    {
        $data = $request->post();

        $site = Db::table('sites')->where('id', $id)->find();
        if (!$site) {
            return json(['status' => 'error', 'message' => 'Site not found'], 404);
        }

        Db::table('sites')->where('id', $id)->update([
            'domain'           => $data['domain']           ?? $site['domain'],
            'site_name'        => $data['site_name']        ?? $site['site_name'],
            'site_description' => $data['site_description'] ?? $site['site_description'],
            'copyright_text'   => $data['copyright_text']   ?? $site['copyright_text'],
            'language'         => $data['language']         ?? $site['language'],
            'keyword_1'        => $data['keyword_1']        ?? $site['keyword_1'],
            'keyword_2'        => $data['keyword_2']        ?? $site['keyword_2'],
            'keyword_3'        => $data['keyword_3']        ?? $site['keyword_3'],
            'publish_type'     => $data['publish_type']     ?? $site['publish_type'],
            'publish_endpoint' => $data['publish_endpoint'] ?? $site['publish_endpoint'],
            'publish_api_key'  => $data['publish_api_key']  ?? $site['publish_api_key'],
            'category'         => $data['category']         ?? $site['category'],
            'status'           => $data['status']           ?? $site['status'],
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return json(['status' => 'success', 'message' => 'Site updated successfully']);
    }

    // DELETE /api/sites/:id
    public function destroy($id)
    {
        $site = Db::table('sites')->where('id', $id)->find();
        if (!$site) {
            return json(['status' => 'error', 'message' => 'Site not found'], 404);
        }

        Db::table('sites')->where('id', $id)->delete();

        return json(['status' => 'success', 'message' => 'Site deleted successfully']);
    }

    // GET /api/sites/:id/articles
    public function articles($id)
    {
        $articles = Db::table('articles')
            ->where('site_id', $id)
            ->order('created_at', 'desc')
            ->limit(10)
            ->select()->toArray();

        return json(['status' => 'success', 'data' => $articles]);
    }
}