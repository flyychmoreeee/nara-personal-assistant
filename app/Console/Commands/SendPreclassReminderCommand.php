<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendPreclassReminderCommand extends Command
{
    protected $signature = 'app:send-preclass-reminders {--dry-run : Run without sending actual WhatsApp messages}';
    protected $description = 'Send reminder messages to Course PICs 1 hour before scheduled classes';

    public function handle(ReminderService $reminderService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Checking schedules starting within the next hour...');
        $results = $reminderService->sendPreclassReminders($dryRun);

        if (empty($results)) {
            $this->info('No classes starting in the next ~1 hour window.');
            return self::SUCCESS;
        }

        $this->table(
            ['Schedule ID', 'PIC', 'Phone', 'Status'],
            array_map(function ($r) {
                return [
                    $r['schedule_id'] ?? '-',
                    $r['pic'] ?? '-',
                    $r['target_phone'] ?? '-',
                    $r['status'] ?? '-',
                ];
            }, $results)
        );

        $this->info('Pre-class reminder checked and completed.');
        return self::SUCCESS;
    }
}
