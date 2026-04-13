<?php
namespace app\controller;

use think\facade\Db;

class SchedulerController
{
    /**
     * Daily trigger — loops through all active sites
     * and queues article generation for each keyword
     */
    public function run()
    {
        $sites = Db::table('sites')->where('status', 'active')->select();

        foreach ($sites as $site) {
            $keywords = array_filter([
                $site['keyword_1'],
                $site['keyword_2'],
                $site['keyword_3'],
            ]);

            $articlesPerDay = $this->getSetting('articles_per_day', 3);
            $keywords = array_slice($keywords, 0, $articlesPerDay);

            foreach ($keywords as $keyword) {
                // Will call ArticleGenerator in day 3
                // ArticleGeneratorController::generate($site, $keyword)
            }
        }

        return json(['status' => 'success', 'message' => 'Scheduler triggered']);
    }

    private function getSetting($key, $default = null)
    {
        $setting = Db::table('settings')->where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }
}