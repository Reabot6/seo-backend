<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class FriendlyLinksController
{
    // GET /api/links?site_id=X
    public function index(Request $request)
    {
        $siteId = $request->get('site_id', '');
        $query  = Db::table('friendly_links')->order('created_at', 'desc');
        if ($siteId) $query->where('site_id', (int)$siteId);

        $links   = $query->select()->toArray();
        $siteIds = array_unique(array_column($links, 'site_id'));
        $sites   = $siteIds ? Db::table('sites')->whereIn('id', $siteIds)->column('domain', 'id') : [];
        foreach ($links as &$l) $l['site_domain'] = $sites[$l['site_id']] ?? '';

        return json(['status' => 'success', 'data' => $links]);
    }

    // POST /api/links
    public function store(Request $request)
    {
        $siteId   = $request->post('site_id');
        $linkUrl  = $request->post('link_url');
        $linkText = $request->post('link_text');

        if (!$siteId || !$linkUrl || !$linkText) {
            return json(['status' => 'error', 'message' => 'site_id, link_url and link_text required'], 400);
        }

        $id = Db::table('friendly_links')->insertGetId([
            'site_id'    => (int)$siteId,
            'link_url'   => $linkUrl,
            'link_text'  => $linkText,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return json(['status' => 'success', 'id' => $id]);
    }

    // POST /api/links/update
    public function update(Request $request)
    {
        $id = $request->post('id');
        if (!$id) return json(['status' => 'error', 'message' => 'No ID'], 400);

        $data = [];
        if ($request->has('link_url'))  $data['link_url']  = $request->post('link_url');
        if ($request->has('link_text')) $data['link_text'] = $request->post('link_text');
        if ($request->has('site_id'))   $data['site_id']   = (int)$request->post('site_id');

        Db::table('friendly_links')->where('id', (int)$id)->update($data);
        return json(['status' => 'success', 'message' => 'Link updated']);
    }

    // DELETE /api/links/delete?id=X
    public function destroy(Request $request)
    {
        $id = $request->get('id');
        if (!$id) return json(['status' => 'error', 'message' => 'No ID'], 400);
        Db::table('friendly_links')->where('id', (int)$id)->delete();
        return json(['status' => 'success', 'message' => 'Deleted']);
    }
}