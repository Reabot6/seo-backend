<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class ScheduledTaskController
{
    // GET /api/tasks?site_id=X
    public function index(Request $request)
    {
        $siteId = $request->get('site_id', '');
        $query  = Db::table('scheduled_tasks');
        if ($siteId) $query->where('site_id', (int)$siteId);

        $tasks = $query->order('created_at', 'desc')->select()->toArray();

        // Attach site domain
        $siteIds = array_unique(array_column($tasks, 'site_id'));
        $sites   = Db::table('sites')->whereIn('id', $siteIds)->column('domain', 'id');
        foreach ($tasks as &$t) $t['site_domain'] = $sites[$t['site_id']] ?? '';

        return json(['status' => 'success', 'data' => $tasks]);
    }

    // POST /api/tasks
    public function store(Request $request)
    {
        $siteId  = $request->post('site_id');
        $keyword = $request->post('keyword');
        if (!$siteId || !$keyword) {
            return json(['status' => 'error', 'message' => 'site_id and keyword required'], 400);
        }

        $id = Db::table('scheduled_tasks')->insertGetId([
            'site_id'          => (int)$siteId,
            'keyword'          => $keyword,
            'language'         => $request->post('language', 'en'),
            'category'         => $request->post('category', ''),
            'media_type'       => $request->post('media_type', 'image'),
            'frequency'        => $request->post('frequency', 'daily'),
            'publish_endpoint' => $request->post('publish_endpoint', ''),
            'publish_api_key'  => $request->post('publish_api_key', ''),
            'status'           => 'active',
            'next_run_at'      => date('Y-m-d H:i:s'),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return json(['status' => 'success', 'id' => $id]);
    }

    // POST /api/tasks/update
    public function update(Request $request)
    {
        $id = $request->post('id');
        if (!$id) return json(['status' => 'error', 'message' => 'No ID'], 400);

        $fields = ['keyword','language','category','media_type','frequency',
                   'publish_endpoint','publish_api_key','status'];
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        foreach ($fields as $f) {
            if ($request->has($f)) $data[$f] = $request->post($f);
        }

        Db::table('scheduled_tasks')->where('id', (int)$id)->update($data);
        return json(['status' => 'success', 'message' => 'Task updated']);
    }

    // DELETE /api/tasks/delete?id=X
    public function destroy(Request $request)
    {
        $id = $request->get('id');
        if (!$id) return json(['status' => 'error', 'message' => 'No ID'], 400);
        Db::table('scheduled_tasks')->where('id', (int)$id)->delete();
        return json(['status' => 'success', 'message' => 'Task deleted']);
    }

    // POST /api/tasks/run — manually trigger a task
    public function run(Request $request)
    {
        $id   = $request->post('id');
        $task = Db::table('scheduled_tasks')->where('id', (int)$id)->find();
        if (!$task) return json(['status' => 'error', 'message' => 'Task not found'], 404);

        $site = Db::table('sites')->where('id', $task['site_id'])->find();
        if (!$site) return json(['status' => 'error', 'message' => 'Site not found'], 404);

        // Override with task-specific settings
        $site['language'] = $task['language'];
        if ($task['publish_endpoint']) $site['publish_endpoint'] = $task['publish_endpoint'];
        if ($task['publish_api_key'])  $site['publish_api_key']  = $task['publish_api_key'];
        if ($task['category'])         $site['category']         = $task['category'];

        $controller = new ArticleGeneratorController();
        $result     = $controller->generateArticle($site, $task['keyword'], $task['media_type']);

        // Update last_run
        Db::table('scheduled_tasks')->where('id', (int)$id)->update([
            'last_run_at' => date('Y-m-d H:i:s'),
            'next_run_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return json($result);
    }
}
