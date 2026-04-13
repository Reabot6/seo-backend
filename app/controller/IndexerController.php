<?php
namespace app\controller;

use think\facade\Db;

class IndexerController
{
    /**
     * Main index method — submits to all enabled engines
     */
    public function index($articleId, $url)
    {
        $results = [];

        if ($this->getSetting('google_indexing', 1)) {
            $results['google'] = $this->submitToGoogle($url);
            if ($results['google']) {
                Db::table('articles')->where('id', $articleId)->update(['google_indexed' => 1]);
            }
        }

        if ($this->getSetting('bing_indexing', 1)) {
            $results['bing'] = $this->submitToBing($url);
            if ($results['bing']) {
                Db::table('articles')->where('id', $articleId)->update(['bing_indexed' => 1]);
            }
        }

        if ($this->getSetting('baidu_indexing', 0)) {
            $results['baidu'] = $this->submitToBaidu($url);
            if ($results['baidu']) {
                Db::table('articles')->where('id', $articleId)->update(['baidu_indexed' => 1]);
            }
        }

        if ($this->getSetting('yandex_indexing', 0)) {
            $results['yandex'] = $this->submitToYandex($url);
            if ($results['yandex']) {
                Db::table('articles')->where('id', $articleId)->update(['yandex_indexed' => 1]);
            }
        }

        if ($this->getSetting('sogou_indexing', 0)) {
            $results['sogou'] = $this->submitToSogou($url);
            if ($results['sogou']) {
                Db::table('articles')->where('id', $articleId)->update(['sogou_indexed' => 1]);
            }
        }

        if ($this->getSetting('360_indexing', 0)) {
            $results['360'] = $this->submitTo360($url);
            if ($results['360']) {
                Db::table('articles')->where('id', $articleId)->update(['indexed_360' => 1]);
            }
        }

        $this->logResults($articleId, $results);

        return json(['status' => 'success', 'results' => $results]);
    }

    // ─────────────────────────────────────────
    // GOOGLE INDEXING API
    // ─────────────────────────────────────────
    private function submitToGoogle($url)
    {
        $apiKey = $this->getSetting('google_api_key');
        if (!$apiKey) return false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://indexing.googleapis.com/v3/urlNotifications:publish',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['url' => $url, 'type' => 'URL_UPDATED']),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\facade\Log::info("Google indexing: HTTP {$httpCode} for {$url}");
        return $httpCode === 200;
    }

    // ─────────────────────────────────────────
    // BING WEBMASTER API
    // ─────────────────────────────────────────
    private function submitToBing($url)
    {
        $apiKey  = $this->getSetting('bing_api_key');
        $siteUrl = $this->getSetting('bing_site_url');

        if (!$apiKey || !$siteUrl) return false;

        $endpoint = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrl?apikey=' . urlencode($apiKey);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['siteUrl' => $siteUrl, 'url' => $url]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\facade\Log::info("Bing indexing: HTTP {$httpCode} for {$url}");
        return $httpCode === 200;
    }

    // ─────────────────────────────────────────
    // BAIDU LINK SUBMISSION API
    // ─────────────────────────────────────────
    private function submitToBaidu($url)
    {
        $apiKey  = $this->getSetting('baidu_api_key');
        $siteUrl = $this->getSetting('baidu_site_url');

        if (!$apiKey || !$siteUrl) return false;

        $endpoint = "http://data.zz.baidu.com/urls?site={$siteUrl}&token={$apiKey}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $url,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/plain'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\facade\Log::info("Baidu indexing: HTTP {$httpCode} for {$url}");
        return $httpCode === 200;
    }

    // ─────────────────────────────────────────
    // YANDEX — uses IndexNow protocol
    // ─────────────────────────────────────────
    private function submitToYandex($url)
    {
        $apiKey = $this->getSetting('yandex_api_key');
        if (!$apiKey) return false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://yandex.com/indexnow',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'host'    => parse_url($url, PHP_URL_HOST),
                'key'     => $apiKey,
                'urlList' => [$url],
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\facade\Log::info("Yandex indexing: HTTP {$httpCode} for {$url}");
        return $httpCode === 200;
    }

    // ─────────────────────────────────────────
    // SOGOU SUBMISSION
    // ─────────────────────────────────────────
    private function submitToSogou($url)
    {
        $apiKey  = $this->getSetting('sogou_api_key');
        $siteUrl = $this->getSetting('sogou_site_url');

        if (!$apiKey || !$siteUrl) return false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'http://zhanzhang.sogou.com/index.php/api/links',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'token' => $apiKey,
                'url'   => $url,
                'site'  => $siteUrl,
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\facade\Log::info("Sogou indexing: HTTP {$httpCode} for {$url}");
        return $httpCode === 200;
    }

    // ─────────────────────────────────────────
    // 360 SUBMISSION
    // ─────────────────────────────────────────
    private function submitTo360($url)
    {
        $apiKey = $this->getSetting('key_360');
        if (!$apiKey) return false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://zhanzhang.so.com/api/url/submit',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['token' => $apiKey, 'url' => $url]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        \think\facade\Log::info("360 indexing: HTTP {$httpCode} for {$url}");
        return $httpCode === 200;
    }

    // ─────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────
    private function logResults($articleId, $results)
    {
        $engines   = array_filter($results);
        $submitted = implode(', ', array_keys($engines));

        Db::table('logs')->insert([
            'article_id' => $articleId,
            'event'      => 'indexed',
            'message'    => $submitted
                ? "Article submitted to: {$submitted}"
                : 'No indexing engines were triggered',
            'status'     => $submitted ? 'success' : 'warning',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getSetting($key, $default = null)
    {
        $setting = Db::table('settings')->where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }
}