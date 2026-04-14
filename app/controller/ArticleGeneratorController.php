<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class ArticleGeneratorController
{
    // Language map for prompt instruction
    private $languageNames = [
        'en' => 'English',
        'zh' => 'Chinese (Simplified)',
        'vi' => 'Vietnamese',
        'id' => 'Indonesian',
        'th' => 'Thai',
        'ko' => 'Korean',
        'ja' => 'Japanese',
        'es' => 'Spanish',
        'tr' => 'Turkish',
        'ar' => 'Arabic',
        'it' => 'Italian',
        'fr' => 'French',
        'hi' => 'Hindi',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'de' => 'German',
    ];

    /**
     * POST /api/article/generate
     * Manual trigger from frontend
     */
  public function generate(Request $request)
{
    $siteId    = $request->post('site_id');
    $keyword   = $request->post('keyword');
    $mediaType = $request->post('media_type', 'image');
    $language  = $request->post('language', null); // add this

    if (!$siteId || !$keyword) {
        return json(['status' => 'error', 'message' => 'site_id and keyword are required'], 400);
    }

    $site = Db::table('sites')->where('id', $siteId)->find();
    if (!$site) {
        return json(['status' => 'error', 'message' => 'Site not found'], 404);
    }

    // Override site language if explicitly passed
    if ($language) {
        $site['language'] = $language;
    }

    $result = $this->generateArticle($site, $keyword, $mediaType);
    return json($result);
}

    /**
     * Core article generation method
     * Called by both manual trigger and scheduler
     */
    public function generateArticle($site, $keyword, $mediaType = 'image')
    {
        $groqKey   = $this->getSetting('groq_api_key');
        $model     = $this->getSetting('groq_model', 'llama-3.1-8b-instant');
        $maxTokens = (int) $this->getSetting('max_tokens', 2048);
        $language  = $site['language'] ?? 'en';
        $langName  = $this->languageNames[$language] ?? 'English';

        if (!$groqKey) {
            return [
                'status'  => 'error',
                'message' => 'Groq API key not configured',
            ];
        }

        // Try up to 3 times for originality
        $maxAttempts = 3;
        $attempt     = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            // Generate article
            $prompt   = $this->buildPrompt($keyword, $site['category'], $langName);
            $response = $this->callGroq($groqKey, $model, $maxTokens, $prompt);

            if (!$response) {
                $this->logError($site['id'], null, "Groq API call failed on attempt {$attempt}");
                continue;
            }

            // Parse response
            $parsed = $this->parseResponse($response);

            if (!$parsed) {
                $this->logError($site['id'], null, "Failed to parse Groq response on attempt {$attempt}");
                continue;
            }

            // Check originality
            $originalityController = new OriginalityController();
            $originality = $originalityController->check($parsed['body']);

            if ($originality['passed']) {
                // Publish article
                $publisherController = new PublisherController();
                $articleId = $publisherController->publish(
                    array_merge($parsed, [
                        'site_id'   => $site['id'],
                        'keyword'   => $keyword,
                        'category'  => $site['category'],
                        'language'  => $language,
                    ]),
                    $originality['score'],
                    $site,
                    $mediaType
                );

                return [
                    'status'     => 'success',
                    'article_id' => $articleId,
                    'title'      => $parsed['title'],
                    'score'      => $originality['score'],
                    'attempts'   => $attempt,
                ];
            }

            // Log failed originality check
            $this->logError(
                $site['id'],
                null,
                "Originality check failed (score: {$originality['score']}) on attempt {$attempt}"
            );
        }

        // All attempts failed
        Db::table('articles')->insert([
            'site_id'           => $site['id'],
            'keyword'           => $keyword,
            'category'          => $site['category'] ?? '',
            'title'             => "Failed article for: {$keyword}",
            'description'       => '',
            'body'              => '',
            'originality_score' => 0,
            'status'            => 'failed',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return [
            'status'  => 'error',
            'message' => "Failed after {$maxAttempts} attempts",
        ];
    }

    /**
     * Build the Groq prompt
     */
    private function buildPrompt($keyword, $category, $language)
    {
        return "Write a detailed, original SEO article in {$language} about '{$keyword}'" .
               ($category ? " in the {$category} category" : "") . ".
 IMPORTANT: Do not use markdown formatting. No asterisks, no bold, no italic. Plain text labels only.\n\n" .
       "Write a detailed, original SEO article in {$language} about '{$keyword}'
STRICT FORMAT — return EXACTLY this structure with these exact labels:
TITLE: [Write an engaging SEO title here]
DESCRIPTION: [Write a compelling meta description of 150-160 characters here]
BODY:
<article>
<h1>[Same as title]</h1>
<p>[Introduction paragraph that naturally includes the keyword '{$keyword}']</p>
<h2>[First main subheading]</h2>
<p>[Detailed paragraph about this subtopic]</p>
<h2>[Second main subheading]</h2>
<p>[Detailed paragraph about this subtopic]</p>
<h2>[Third main subheading]</h2>
<p>[Detailed paragraph about this subtopic]</p>
<h2>Conclusion</h2>
<p>[Strong concluding paragraph that includes the keyword naturally]</p>
</article>

RULES:
- Write entirely in {$language}
- Use <h1> ONLY ONCE for the title
- Use <h2> and <h3> for subheadings only
- Naturally insert '{$keyword}' throughout
- Do not include <html>, <head>, or <body> tags
- Do not add any CSS or JavaScript
- Minimum 600 words in the body
- Make it 100% original and valuable";
    }

    /**
     * Call Groq API via cURL
     */
    private function callGroq($apiKey, $model, $maxTokens, $prompt)
    {
        $url  = 'https://api.groq.com/openai/v1/chat/completions';
        $data = json_encode([
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.7,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $decoded = json_decode($response, true);
        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Parse Groq response into title, description, body
     */
  private function parseResponse($response)
{
    if (!$response) return null;

    // Strip markdown bold formatting ** from labels
    $response = preg_replace('/\*\*(TITLE:|DESCRIPTION:|BODY:)\*\*/', '$1', $response);
    $response = preg_replace('/\*\*<article>\*\*/', '<article>', $response);
    $response = preg_replace('/\*\*<\/article>\*\*/', '</article>', $response);

    // Also strip ** from HTML tags like **<h1>** -> <h1>
    $response = preg_replace('/\*\*(<[^>]+>)\*\*/', '$1', $response);
    $response = preg_replace('/\*\*(<\/[^>]+>)\*\*/', '$1', $response);

    // Extract TITLE
    if (!preg_match('/TITLE:\s*(.+?)(?:\n|DESCRIPTION:)/s', $response, $titleMatch)) {
        return null;
    }
    $title = trim($titleMatch[1]);

    // Extract DESCRIPTION
    if (!preg_match('/DESCRIPTION:\s*(.+?)(?:\n\n|BODY:|<article>)/s', $response, $descMatch)) {
        return null;
    }
    $description = trim($descMatch[1]);

    // Extract BODY — try with BODY: label first
    if (preg_match('/BODY:\s*(.+)/s', $response, $bodyMatch)) {
        $body = trim($bodyMatch[1]);
    } elseif (preg_match('/<article>(.+)/s', $response, $bodyMatch)) {
        // Fallback — grab from <article> tag
        $body = '<article>' . trim($bodyMatch[1]);
    } else {
        return null;
    }

    // Strip remaining ** markdown from body
    $body = preg_replace('/\*\*/', '', $body);

    if (empty($title) || empty($body)) {
        return null;
    }

    return [
        'title'       => $title,
        'description' => $description,
        'body'        => $body,
    ];
}

    private function getSetting($key, $default = null)
    {
        $setting = Db::table('settings')->where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }

    private function logError($siteId, $articleId, $message)
    {
        Db::table('logs')->insert([
            'site_id'    => $siteId,
            'article_id' => $articleId,
            'event'      => 'failed',
            'message'    => $message,
            'status'     => 'error',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
    /**
 * GET /api/article/test-groq
 * Debug endpoint to see raw Groq response
 */
public function testGroq()
{
    $groqKey = $this->getSetting('groq_api_key');
    $model   = $this->getSetting('groq_model', 'llama-3.1-8b-instant');

    if (!$groqKey) {
        return json(['status' => 'error', 'message' => 'No Groq API key']);
    }

    $prompt   = $this->buildPrompt('seo tips', 'Technology', 'English');
    $response = $this->callGroq($groqKey, $model, 500, $prompt);

    return json([
        'status'   => 'success',
        'response' => $response,
        'parsed'   => $this->parseResponse($response ?? ''),
    ]);
}
}