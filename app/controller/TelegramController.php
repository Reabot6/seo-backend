<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class TelegramController
{
    /**
     * Send log message to Telegram channel/group
     */
    public function send($event, $message, $siteId = null, $articleId = null)
    {
        $token  = $this->getSetting('tg_bot_token');
        $chatId = $this->getSetting('tg_chat_id');

        if (!$token || !$chatId) return false;

        // Check if this event type is enabled
        $eventsJson = $this->getSetting('tg_events', '{}');
        $events     = json_decode($eventsJson, true);

        if (isset($events[$event]) && !$events[$event]) return false;

        $text = $this->formatMessage($event, $message);
        $sent = $this->callTelegramApi($token, $chatId, $text);

        // Log it
        Db::table('logs')->insert([
            'site_id'    => $siteId,
            'article_id' => $articleId,
            'event'      => $event,
            'message'    => $message,
            'status'     => $sent ? 'success' : 'error',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $sent;
    }

    /**
     * POST /api/telegram/send
     * Manual trigger from frontend test button
     */
    public function sendTest(Request $request)
    {
        $token  = $this->getSetting('tg_bot_token');
        $chatId = $this->getSetting('tg_chat_id');

        if (!$token || !$chatId) {
            return json([
                'status'  => 'error',
                'message' => 'Telegram bot token or chat ID not configured',
            ], 400);
        }

        $text = $this->formatMessage('published',
            "🧪 Test message from SEOForge\n" .
            "✅ Your Telegram bot is working correctly!\n" .
            "🕐 " . date('Y-m-d H:i:s')
        );

        $sent = $this->callTelegramApi($token, $chatId, $text);

        if ($sent) {
            return json([
                'status'  => 'success',
                'message' => 'Test message sent successfully',
            ]);
        }

        return json([
            'status'  => 'error',
            'message' => 'Failed to send test message. Check your token and chat ID.',
        ], 500);
    }

    /**
     * Format message with emoji and structure
     */
    private function formatMessage($event, $message)
    {
        $emoji = [
            'published'   => '✅',
            'failed'      => '❌',
            'originality' => '⚠️',
            'indexed'     => '🔍',
        ];

        $icon = $emoji[$event] ?? 'ℹ️';

        return "<b>SEOForge Notification</b>\n\n" .
               "{$icon} <b>" . ucfirst($event) . "</b>\n\n" .
               "{$message}\n\n" .
               "🕐 " . date('Y-m-d H:i:s');
    }

    /**
     * Call Telegram Bot API via cURL
     */
    private function callTelegramApi($token, $chatId, $text)
    {
        $url  = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = json_encode([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return false;

        $decoded = json_decode($response, true);
        return $decoded['ok'] ?? false;
    }

    private function getSetting($key, $default = null)
    {
        $setting = Db::table('settings')->where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }
}