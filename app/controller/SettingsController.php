<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class SettingsController
{
    /**
     * GET /api/settings
     * Returns all settings as key/value object
     */
    public function index()
    {
        $rows = Db::table('settings')->select();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    /**
     * GET /api/settings/language
     * Returns active language for frontend
     */
    public function language()
    {
        $setting = Db::table('settings')
            ->where('key', 'default_language')
            ->find();

        return json([
            'status'   => 'success',
            'language' => $setting ? $setting['value'] : 'en',
        ]);
    }

    /**
     * POST /api/settings
     * Saves all settings from frontend
     * Body: { key: value, key: value ... }
     */
    public function save(Request $request)
    {
        $data = $request->post();

        if (empty($data)) {
            return json([
                'status'  => 'error',
                'message' => 'No data provided',
            ], 400);
        }

        foreach ($data as $key => $value) {
            $existing = Db::table('settings')
                ->where('key', $key)
                ->find();

            if ($existing) {
                Db::table('settings')
                    ->where('key', $key)
                    ->update([
                        'value'      => $value,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                Db::table('settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return json([
            'status'  => 'success',
            'message' => 'Settings saved successfully',
        ]);
    }

    /**
     * POST /api/settings/single
     * Save a single setting
     * Body: { key: "groq_api_key", value: "gsk_..." }
     */
    public function saveSingle(Request $request)
    {
        $key   = $request->post('key');
        $value = $request->post('value');

        if (!$key) {
            return json([
                'status'  => 'error',
                'message' => 'Key is required',
            ], 400);
        }

        $existing = Db::table('settings')
            ->where('key', $key)
            ->find();

        if ($existing) {
            Db::table('settings')
                ->where('key', $key)
                ->update([
                    'value'      => $value,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            Db::table('settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return json([
            'status'  => 'success',
            'message' => "Setting '{$key}' saved successfully",
        ]);
    }

    /**
     * GET /api/settings/group/indexing
     * Returns only indexing related settings
     */
    public function indexingSettings()
    {
        $keys = [
            'google_api_key',
            'google_property',
            'bing_api_key',
            'baidu_api_key',
            'yandex_api_key',
            'sogou_api_key',
            'key_360',
            'google_indexing',
            'bing_indexing',
            'baidu_indexing',
            'yandex_indexing',
            'sogou_indexing',
            '360_indexing',
        ];

        $rows = Db::table('settings')
            ->whereIn('key', $keys)
            ->select();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    /**
     * GET /api/settings/group/ai
     * Returns only AI related settings
     */
    public function aiSettings()
    {
        $keys = [
            'groq_api_key',
            'groq_model',
            'max_tokens',
            'originality_threshold',
            'default_language',
        ];

        $rows = Db::table('settings')
            ->whereIn('key', $keys)
            ->select();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    /**
     * GET /api/settings/group/telegram
     * Returns only Telegram related settings
     */
    public function telegramSettings()
    {
        $keys = [
            'tg_bot_token',
            'tg_chat_id',
            'tg_events',
        ];

        $rows = Db::table('settings')
            ->whereIn('key', $keys)
            ->select();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    /**
     * GET /api/settings/group/scheduler
     * Returns only scheduler related settings
     */
    public function schedulerSettings()
    {
        $keys = [
            'articles_per_day',
            'schedule_time',
            'timezone',
            'scheduler_enabled',
        ];

        $rows = Db::table('settings')
            ->whereIn('key', $keys)
            ->select();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }
}