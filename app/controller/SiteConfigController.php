<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class SiteConfigController
{
    // GET /api/site/config?site_id=X
    public function show(Request $request)
    {
        $siteId = $request->get('site_id');
        if (!$siteId) return json(['status' => 'error', 'message' => 'site_id required'], 400);

        $site = Db::table('sites')->where('id', (int)$siteId)->find();
        if (!$site) return json(['status' => 'error', 'message' => 'Site not found'], 404);

        // Decode JSON fields
        $site['nav_links']  = $site['nav_links']  ? json_decode($site['nav_links'], true)  : [];
        $site['categories'] = $site['categories'] ? json_decode($site['categories'], true) : [];

        return json(['status' => 'success', 'data' => $site]);
    }

    // POST /api/site/config
    public function update(Request $request)
    {
        $siteId = $request->post('site_id');
        if (!$siteId) return json(['status' => 'error', 'message' => 'site_id required'], 400);

        $site = Db::table('sites')->where('id', (int)$siteId)->find();
        if (!$site) return json(['status' => 'error', 'message' => 'Site not found'], 404);

        $fields = [
            'site_name', 'short_name', 'site_description', 'site_tags',
            'copyright_text', 'analytics_code', 'support_url', 'support_label',
            'language',
        ];

        $data = [];
        foreach ($fields as $f) {
            if ($request->has($f)) $data[$f] = $request->post($f);
        }

        // JSON fields
        if ($request->has('nav_links'))  $data['nav_links']  = json_encode($request->post('nav_links'));
        if ($request->has('categories')) $data['categories'] = json_encode($request->post('categories'));

        $data['updated_at'] = date('Y-m-d H:i:s');

        Db::table('sites')->where('id', (int)$siteId)->update($data);
        return json(['status' => 'success', 'message' => 'Config updated']);
    }
}