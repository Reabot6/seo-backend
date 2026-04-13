<?php
namespace app\controller;

use think\facade\Db;

class PublisherController
{
    /**
     * Publish to WordPress via REST API
     */
    private function publishToWordPress($site, $article, $body)
    {
        $endpoint = rtrim($site['publish_endpoint'], '/');
        $apiKey   = $site['publish_api_key']; // format: username:application_password

        $url  = "{$endpoint}/wp-json/wp/v2/posts";
        $data = json_encode([
            'title'   => $article['title'],
            'content' => $body,
            'excerpt' => $article['description'],
            'status'  => 'publish',
            'tags'    => [],
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($apiKey),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            $this->log(
                $site['id'], null, 'failed',
                "WordPress publish failed (HTTP {$httpCode}): {$response}", 'error'
            );
            return null;
        }

        $decoded = json_decode($response, true);
        return $decoded['link'] ?? null;
    }

    /**
     * Publish to custom CMS via REST API
     */
    private function publishToCustom($site, $article, $body)
    {
        $endpoint = rtrim($site['publish_endpoint'], '/');
        $apiKey   = $site['publish_api_key'];

        $data = json_encode([
            'title'       => $article['title'],
            'description' => $article['description'],
            'body'        => $body,
            'keyword'     => $article['keyword'],
            'category'    => $article['category'] ?? '',
            'language'    => $article['language'] ?? 'en',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode > 299) {
            $this->log(
                $site['id'], null, 'failed',
                "Custom publish failed (HTTP {$httpCode}): {$response}", 'error'
            );
            return null;
        }

        $decoded = json_decode($response, true);
        return $decoded['url'] ?? $decoded['link'] ?? $endpoint;
    }

    /**
     * Publish article — save to DB + build HTML + index + notify
     */
    public function publish($article, $originalityScore, $site, $mediaType = 'image')
    {
        $media   = $this->getMedia($article['keyword'], $mediaType);
        $body    = $this->buildPageHTML($site, $article, $media);

        // Route to correct publisher based on site type
        $publishType = $site['publish_type'] ?? 'wordpress';
        $articleUrl  = null;

        if ($publishType === 'wordpress') {
            $articleUrl = $this->publishToWordPress($site, $article, $body);
        } else {
            $articleUrl = $this->publishToCustom($site, $article, $body);
        }

        // Fallback URL if publish failed
        if (!$articleUrl) {
            $articleUrl = $this->buildArticleUrl($site, 0);
        }

        // Save article record to DB
        $articleId = Db::table('articles')->insertGetId([
            'site_id'           => $article['site_id'],
            'title'             => $article['title'],
            'description'       => $article['description'],
            'body'              => $body,
            'keyword'           => $article['keyword'],
            'category'          => $article['category'] ?? '',
            'originality_score' => $originalityScore,
            'status'            => $articleUrl ? 'published' : 'failed',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        // Attach media
        foreach ($media as $item) {
            Db::table('article_media')->insert([
                'article_id' => $articleId,
                'media_id'   => $item['id'],
            ]);
        }

        // Update site article count
      Db::table('sites')
    ->where('id', $article['site_id'])
    ->update([
        'articles_count' => Db::raw('articles_count + 1'),
        'updated_at'     => date('Y-m-d H:i:s'),
    ]);

        // Log
        $this->log($article['site_id'], $articleId, 'published',
            "Article published: {$article['title']}", 'success');

        // Index + notify only if publish succeeded
        if ($articleUrl) {
            $indexer = new IndexerController();
            $indexer->index($articleId, $articleUrl);

            $telegram = new TelegramController();
            $telegram->send(
                'published',
                "Article published on {$site['domain']}\n" .
                "Title: {$article['title']}\n" .
                "Keyword: {$article['keyword']}\n" .
                "URL: {$articleUrl}",
                $article['site_id'],
                $articleId
            );
        }

        return $articleId;
    }

    /**
     * Build full SEO page HTML
     */
    private function buildPageHTML($site, $article, $media)
    {
        $language    = $site['language']        ?? 'en';
        $siteName    = htmlspecialchars($site['site_name']        ?? $site['domain']);
        $siteDesc    = htmlspecialchars($site['site_description'] ?? '');
        $copyright   = htmlspecialchars($site['copyright_text']  ?? "© " . date('Y') . " {$siteName}");
        $title       = htmlspecialchars($article['title']);
        $description = htmlspecialchars($article['description']);
        $body        = $article['body'];
        $date        = date('Y-m-d');

        // Get first image for OG tag
        $ogImage = '';
        foreach ($media as $item) {
            if ($item['type'] === 'image') {
                $ogImage = $item['filepath'];
                break;
            }
        }

        // Insert media into body
        $body = $this->insertMediaIntoBody($body, $media);

        // Get friendly links
        $friendlyLinks = $this->getFriendlyLinks($site['id']);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="{$language}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$siteName} - {$title}</title>
  <meta name="description" content="{$description}">
  <link rel="canonical" href="">

  <!-- Open Graph -->
  <meta property="og:title" content="{$title}">
  <meta property="og:description" content="{$description}">
  <meta property="og:type" content="article">
  <meta property="og:image" content="{$ogImage}">
  <meta property="og:site_name" content="{$siteName}">

  <!-- Schema.org JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "{$title}",
    "description": "{$description}",
    "author": {
      "@type": "Organization",
      "name": "{$siteName}"
    },
    "publisher": {
      "@type": "Organization",
      "name": "{$siteName}"
    },
    "datePublished": "{$date}",
    "dateModified": "{$date}",
    "image": "{$ogImage}"
  }
  </script>
</head>
<body>

  <!-- Site Header -->
  <header>
    <h2>{$siteName}</h2>
    <p>{$siteDesc}</p>
  </header>

  <!-- Article Content -->
  <main>
    <article>
      {$body}
    </article>
  </main>

  <!-- Footer -->
  <footer>
    <p>{$copyright}</p>
    <nav>
      {$friendlyLinks}
    </nav>
  </footer>

</body>
</html>
HTML;

        return $html;
    }

    /**
     * Insert media into article body at natural points
     */
    private function insertMediaIntoBody($body, $media)
    {
        if (empty($media)) return $body;

        $images = array_filter($media, fn($m) => $m['type'] === 'image');
        $videos = array_filter($media, fn($m) => $m['type'] === 'video');

        // Insert first image after first <p> tag
        if (!empty($images)) {
            $img   = reset($images);
            $alt   = htmlspecialchars($img['tags'] ?? 'article image');
            $imgHtml = "<img src=\"{$img['filepath']}\" alt=\"{$alt}\" style=\"max-width:100%;height:auto;\">";
            $body  = preg_replace('/<\/p>/', "</p>{$imgHtml}", $body, 1);
        }

        // Insert video after second <h2> tag
        if (!empty($videos)) {
            $vid     = reset($videos);
            $vidHtml = "<video controls style=\"max-width:100%;\"><source src=\"{$vid['filepath']}\"></video>";
            $count   = 0;
            $body    = preg_replace_callback('/<h2>/', function($match) use (&$count, $vidHtml) {
                $count++;
                return $count === 2 ? $vidHtml . $match[0] : $match[0];
            }, $body);
        }

        return $body;
    }

    /**
     * Get media by keyword tag
     */
    private function getMedia($keyword, $mediaType = 'image')
    {
        $query = Db::table('media')->whereLike('tags', "%{$keyword}%");

        if ($mediaType === 'image') {
            $query->where('type', 'image');
        } elseif ($mediaType === 'video') {
            $query->where('type', 'video');
        }

        return $query->limit(3)->select()->toArray();
    }

    /**
     * Get friendly links for footer
     */
    private function getFriendlyLinks($siteId)
    {
        $links = Db::table('friendly_links')
            ->where('site_id', $siteId)
            ->select()->toArray();

        $html = '';
        foreach ($links as $link) {
            $url  = htmlspecialchars($link['link_url']);
            $text = htmlspecialchars($link['link_text']);
            $html .= "<a href=\"{$url}\" rel=\"nofollow\">{$text}</a> ";
        }

        return $html;
    }

    /**
     * Build article URL
     */
    private function buildArticleUrl($site, $articleId)
    {
        $domain = rtrim($site['domain'], '/');
        if (!str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }
        return "{$domain}/article/{$articleId}";
    }

    private function log($siteId, $articleId, $event, $message, $status = 'success')
    {
        Db::table('logs')->insert([
            'site_id'    => $siteId,
            'article_id' => $articleId,
            'event'      => $event,
            'message'    => $message,
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}