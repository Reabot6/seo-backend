<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\controller\ArticleGeneratorController;

class RunScheduledTasks extends Command
{
    protected function configure()
    {
        $this->setName('tasks:run')->setDescription('Run due scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        $now  = date('Y-m-d H:i:s');
        $tasks = Db::table('scheduled_tasks')
            ->where('status', 'active')
            ->where('next_run_at', '<=', $now)
            ->select()->toArray();

        if (empty($tasks)) {
            $output->writeln('No tasks due.');
            return;
        }

        $output->writeln('Found ' . count($tasks) . ' due task(s).');

        $controller = new ArticleGeneratorController();

        foreach ($tasks as $task) {
            $site = Db::table('sites')->where('id', $task['site_id'])->find();
            if (!$site || $site['status'] === 'paused') {
                $output->writeln("Skipping task {$task['id']} — site missing or paused");
                continue;
            }

            // Override site with task-specific settings
            $site['language'] = $task['language'];
            if ($task['publish_endpoint']) $site['publish_endpoint'] = $task['publish_endpoint'];
            if ($task['publish_api_key'])  $site['publish_api_key']  = $task['publish_api_key'];
            if ($task['category'])         $site['category']         = $task['category'];

            $output->writeln("Running task {$task['id']}: {$task['keyword']}");

            $result = $controller->generateArticle($site, $task['keyword'], $task['media_type']);

            // Calculate next run
            $nextRun = match($task['frequency']) {
                'weekly' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'manual' => null,
                default  => date('Y-m-d H:i:s', strtotime('+1 day')),
            };

            $update = [
                'last_run_at' => $now,
                'updated_at'  => $now,
            ];
            if ($nextRun) $update['next_run_at'] = $nextRun;
            // If manual, pause it after running
            if ($task['frequency'] === 'manual') $update['status'] = 'paused';

            Db::table('scheduled_tasks')->where('id', $task['id'])->update($update);

            $status = $result['status'] === 'success' ? "✓ {$result['title']}" : "✗ {$result['message']}";
            $output->writeln("  → {$status}");
        }

        $output->writeln('Done.');
    }
}