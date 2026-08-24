<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendMorningReminderCommand extends Command
{
    protected $signature = 'app:send-morning-reminders {--dry-run : Run without sending actual WhatsApp messages} {--day= : Override day name, e.g. monday}';
    protected $description = 'Send morning reminder messages to Course PICs for today\'s scheduled classes';

    public function handle(ReminderService $reminderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customDay = $this->option('day');

        $this->info('Starting morning reminder execution...');
        if ($dryRun) {
            $this->warn('DRY RUN mode activated - No real messages will be sent.');
        }

        $results = $reminderService->sendMorningReminders($dryRun, $customDay);

        if (empty($results)) {
            $this->info('No schedules or reminders to process today.');
            return self::SUCCESS;
        }

        $this->table(
            ['Schedule ID', 'PIC / Course', 'Status', 'Details'],
            array_map(function ($r) {
                return [
                    $r['schedule_id'] ?? '-',
                    $r['pic'] ?? ($r['course'] ?? '-'),
                    $r['status'] ?? '-',
                    isset($r['message']) ? substr($r['message'], 0, 50) . '...' : ($r['reason'] ?? json_encode($r['response'] ?? '')),
                ];
            }, $results)
        );

        $this->info('Morning reminder completed.');
        return self::SUCCESS;
    }
}
