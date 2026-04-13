<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\controller\ArticleGeneratorController;

class RunScheduler extends Command
{
    protected function configure()
    {
       $this->setName('scheduler')
     ->setDescription('Run the daily article generation scheduler');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('═══════════════════════════════════════');
        $output->writeln('SEOForge Scheduler — ' . date('Y-m-d H:i:s'));
        $output->writeln('═══════════════════════════════════════');

        // Check if scheduler is enabled
        $enabled = Db::table('settings')
            ->where('key', 'scheduler_enabled')
            ->value('value');

        if (!$enabled || $enabled === '0') {
            $output->writeln('Scheduler is disabled. Exiting.');
            return;
        }

        // Get settings
        $articlesPerDay = (int) $this->getSetting('articles_per_day', 3);

        // Get all active sites
        $sites = Db::table('sites')
            ->where('status', 'active')
            ->select()->toArray();

        $output->writeln("Found " . count($sites) . " active sites");
        $output->writeln("Articles per site per day: {$articlesPerDay}");
        $output->writeln('───────────────────────────────────────');

        $totalGenerated = 0;
        $totalFailed    = 0;

        $generator = new ArticleGeneratorController();

        foreach ($sites as $site) {
            $output->writeln("\n🌐 Processing: {$site['domain']}");

            // Get keywords for this site
            $keywords = array_filter([
                $site['keyword_1'],
                $site['keyword_2'],
                $site['keyword_3'],
            ]);

            // Limit to articlesPerDay
            $keywords = array_slice($keywords, 0, $articlesPerDay);

            if (empty($keywords)) {
                $output->writeln("  ⚠️  No keywords configured — skipping");
                continue;
            }

            foreach ($keywords as $keyword) {
                $output->writeln("  📝 Generating: {$keyword}");

                $result = $generator->generateArticle($site, $keyword);

                if ($result['status'] === 'success') {
                    $output->writeln("  ✅ Published: {$result['title']}");
                    $output->writeln("     Score: {$result['score']} | Attempts: {$result['attempts']}");
                    $totalGenerated++;
                } else {
                    $output->writeln("  ❌ Failed: " . ($result['message'] ?? 'Unknown error'));
                    $totalFailed++;
                }
            }
        }

        $output->writeln("\n═══════════════════════════════════════");
        $output->writeln("Scheduler complete!");
        $output->writeln("✅ Generated: {$totalGenerated}");
        $output->writeln("❌ Failed: {$totalFailed}");
        $output->writeln('═══════════════════════════════════════');

        // Log completion
        Db::table('logs')->insert([
            'event'      => 'published',
            'message'    => "Scheduler completed: {$totalGenerated} generated, {$totalFailed} failed",
            'status'     => 'success',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getSetting($key, $default = null)
    {
        $setting = Db::table('settings')->where('key', $key)->find();
        return $setting ? $setting['value'] : $default;
    }
}