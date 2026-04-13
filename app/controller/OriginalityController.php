<?php
namespace app\controller;

use think\facade\Db;

class OriginalityController
{
    /**
     * Check originality of article body
     * Uses local uniqueness scoring — fast and free
     */
    public function check($articleBody)
    {
        $threshold = (int) $this->getSetting('originality_threshold', 85);
        $score     = $this->calculateScore($articleBody);

        $passed = $score >= $threshold;

        return [
            'passed' => $passed,
            'score'  => $score,
            'action' => $passed ? 'publish' : 'regenerate',
        ];
    }

    /**
     * Calculate originality score based on:
     * - Sentence length variety
     * - Vocabulary richness
     * - No repeated phrases
     */
    private function calculateScore($html)
    {
        // Strip HTML tags
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (empty($text)) return 0;

        $words = explode(' ', strtolower($text));
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        $words = array_values($words);

        if (count($words) < 50) return 70;

        $totalWords  = count($words);
        $uniqueWords = count(array_unique($words));

        // Vocabulary richness score (unique words / total words)
        $vocabularyScore = ($uniqueWords / $totalWords) * 100;

        // Sentence variety score
        $sentences     = preg_split('/[.!?]+/', $text);
        $sentences     = array_filter($sentences, fn($s) => strlen(trim($s)) > 10);
        $sentenceLens  = array_map(fn($s) => str_word_count($s), $sentences);
        $avgLen        = count($sentenceLens) > 0 ? array_sum($sentenceLens) / count($sentenceLens) : 0;
        $varietyScore  = min(100, ($avgLen > 8 && $avgLen < 40) ? 90 : 70);

        // Repeated phrase penalty
        $phrases       = [];
        for ($i = 0; $i < count($words) - 3; $i++) {
            $phrase = implode(' ', array_slice($words, $i, 3));
            $phrases[] = $phrase;
        }
        $uniquePhrases    = count(array_unique($phrases));
        $totalPhrases     = count($phrases);
        $phraseScore      = $totalPhrases > 0
            ? min(100, ($uniquePhrases / $totalPhrases) * 120)
            : 90;

        // Combined score
        $score = ($vocabularyScore * 0.4) + ($varietyScore * 0.3) + ($phraseScore * 0.3);
        $score = min(99, max(0, round($score)));

        return $score;
    }

    private function getSetting($key, $default = null)
    {
        $setting = Db::table('settings')->where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }
}