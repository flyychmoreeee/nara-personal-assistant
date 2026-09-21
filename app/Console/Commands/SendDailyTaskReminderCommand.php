<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendDailyTaskReminderCommand extends Command
{
    protected $signature = 'app:send-daily-task-reminders 
                            {--dry-run : Run without sending actual WhatsApp messages} 
                            {--group= : Override target WhatsApp group ID} 
                            {--force : Send reminder even if already sent today} 
                            {--skip-empty : Skip sending if there are no pending tasks}';

    protected $description = 'Send daily task reminder (18:00 WIB) to the authorized class WhatsApp group';

    public function handle(ReminderService $reminderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customGroup = $this->option('group');
        $force = (bool) $this->option('force');
        $skipEmpty = (bool) $this->option('skip-empty');

        $this->info('Starting 18:00 WIB daily task reminder execution...');
        if ($dryRun) {
            $this->warn('DRY RUN mode activated - No real WhatsApp message will be sent.');
        }

        $result = $reminderService->sendDailyTaskReminder($dryRun, $customGroup, $force, $skipEmpty);

        $status = $result['status'] ?? 'unknown';

        if ($status === 'skipped') {
            $this->warn("Task reminder skipped. Reason: " . ($result['reason'] ?? '-'));
            return self::SUCCESS;
        }

        $this->table(
            ['Parameter', 'Value'],
            [
                ['Target Group', $result['group'] ?? '-'],
                ['Target Date', $result['target_date'] ?? '-'],
                ['Pending Tasks', (string) ($result['task_count'] ?? 0)],
                ['Status', $status],
            ]
        );

        if (!empty($result['message'])) {
            $this->line("\n<info>Message Preview:</info>\n");
            $this->line($result['message']);
            $this->line("");
        }

        $this->info('Daily task reminder completed.');
        return self::SUCCESS;
    }
}
