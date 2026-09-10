<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendDMinusOneReminderCommand extends Command
{
    protected $signature = 'app:send-d-minus-one-reminders {--dry-run : Run without sending actual WhatsApp messages} {--day= : Override tomorrow\'s day name, e.g. tuesday} {--ignore-holiday : Run reminders even if tomorrow is a holiday}';
    protected $description = 'Send H-1 afternoon reminder messages (16:00 WIB) to Course PICs for tomorrow\'s scheduled classes';

    public function handle(ReminderService $reminderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customDay = $this->option('day');
        $ignoreHoliday = (bool) $this->option('ignore-holiday');

        $this->info('Starting H-1 afternoon reminder execution...');
        if ($dryRun) {
            $this->warn('DRY RUN mode activated - No real messages will be sent.');
        }

        $results = $reminderService->sendDMinusOneReminders($dryRun, $customDay, $ignoreHoliday);

        if (empty($results)) {
            $this->info('No schedules or reminders to process for tomorrow.');
            return self::SUCCESS;
        }

        $this->table(
            ['Schedule ID', 'PIC / Course', 'Target Day / Date', 'Status', 'Details'],
            array_map(function ($r) {
                return [
                    $r['schedule_id'] ?? '-',
                    $r['pic'] ?? ($r['course'] ?? '-'),
                    ($r['target_day'] ?? '-') . ' (' . ($r['target_date'] ?? '-') . ')',
                    $r['status'] ?? '-',
                    isset($r['message']) ? substr($r['message'], 0, 50) . '...' : ($r['reason'] ?? json_encode($r['response'] ?? '')),
                ];
            }, $results)
        );

        $this->info('H-1 afternoon reminder completed.');
        return self::SUCCESS;
    }
}
