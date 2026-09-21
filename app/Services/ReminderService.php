<?php

namespace App\Services;

use App\Models\ReminderLog;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    protected FonnteService $fonnte;
    protected HolidayService $holidayService;

    public function __construct(FonnteService $fonnte, HolidayService $holidayService)
    {
        $this->fonnte = $fonnte;
        $this->holidayService = $holidayService;
    }

    /**
     * Translate English day name to Indonesian
     */
    public function translateDayToIndo(string $day): string
    {
        $days = [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
        ];

        return $days[strtolower($day)] ?? ucfirst($day);
    }

    /**
     * Send H-1 afternoon reminder (at 16:00) to course PICs for tomorrow's scheduled classes
     */
    public function sendDMinusOneReminders(bool $dryRun = false, ?string $customDay = null, bool $ignoreHoliday = false): array
    {
        $targetDate = now()->addDay()->toDateString();
        $targetDayName = $customDay ? strtolower($customDay) : strtolower(now()->addDay()->format('l'));

        if (!$ignoreHoliday && $this->holidayService->isHoliday($targetDate)) {
            $holiday = $this->holidayService->getHoliday($targetDate);
            $holidayName = $holiday ? $holiday->name : 'Hari Libur';
            Log::info("D-1 reminders skipped: Tomorrow ({$targetDate}) is a holiday ({$holidayName}).");
            return [];
        }

        $activeSemester = Semester::where('is_active', true)->first();

        $schedulesQuery = Schedule::query()
            ->where('is_active', true)
            ->where('day', $targetDayName)
            ->with(['course', 'lecturer', 'coursePic']);

        if ($activeSemester) {
            $schedulesQuery->whereHas('course', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $schedules = $schedulesQuery->get();
        $results = [];
        $dayIndo = $this->translateDayToIndo($targetDayName);

        foreach ($schedules as $schedule) {
            $pic = $schedule->coursePic;
            $lecturer = $schedule->lecturer;
            $course = $schedule->course;

            if (!$pic || !$pic->phone_number) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'course' => $course->name ?? '-',
                    'status' => 'skipped',
                    'reason' => 'No Course PIC or phone number assigned',
                ];
                continue;
            }

            // Check if already sent for this target date
            $alreadySent = ReminderLog::where('schedule_id', $schedule->id)
                ->where('type', 'd_minus_1')
                ->where('reminder_date', $targetDate)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent && !$dryRun) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'course' => $course->name,
                    'status' => 'skipped',
                    'reason' => 'Already sent for target date',
                ];
                continue;
            }

            $lecturerSalutation = $lecturer->salutation ?? 'Bapak/Ibu';
            $lecturerName = $lecturer->name ?? 'Dosen Pengampu';
            $lecturerPhone = $lecturer->phone_number ?? '-';
            $startTime = substr($schedule->start_time, 0, 5);
            $endTime = substr($schedule->end_time, 0, 5);

            $message = "Selamat sore {$pic->name},\n\n"
                . "Jangan lupa untuk menghubungi {$lecturerSalutation} {$lecturerName} bahwa besok ({$dayIndo}) akan ada perkuliahan:\n\n"
                . "📚 {$course->name}\n"
                . "⏰ Pukul: {$startTime} - {$endTime} WIB\n"
                . "📞 Nomor Dosen: {$lecturerPhone}\n\n"
                . "Mohon konfirmasi kesediaan beliau ya!\n\n"
                . "— Pesan ini dikirim otomatis.";

            if ($dryRun) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'pic' => $pic->name,
                    'target_phone' => $pic->phone_number,
                    'target_day' => $targetDayName,
                    'target_date' => $targetDate,
                    'status' => 'dry_run',
                    'message' => $message,
                ];
                continue;
            }

            $sendResult = $this->fonnte->sendMessage($pic->phone_number, $message);
            $status = $sendResult['status'] === 'sent' ? 'sent' : 'failed';

            ReminderLog::create([
                'schedule_id' => $schedule->id,
                'type' => 'd_minus_1',
                'reminder_date' => $targetDate,
                'target_phone' => $pic->phone_number,
                'status' => $status,
                'message' => $message,
                'response_payload' => $sendResult['data'] ?? ['error' => $sendResult['message']],
                'sent_at' => $status === 'sent' ? now() : null,
            ]);

            $results[] = [
                'schedule_id' => $schedule->id,
                'pic' => $pic->name,
                'target_phone' => $pic->phone_number,
                'target_day' => $targetDayName,
                'target_date' => $targetDate,
                'status' => $status,
                'response' => $sendResult,
            ];
        }

        return $results;
    }

    /**
     * Send morning reminder to all course PICs whose courses are scheduled today
     */
    public function sendMorningReminders(bool $dryRun = false, ?string $customDay = null, bool $ignoreHoliday = false): array
    {
        $todayName = $customDay ? strtolower($customDay) : strtolower(now()->format('l'));
        $todayDate = now()->toDateString();

        if (!$ignoreHoliday && $this->holidayService->isHoliday($todayDate)) {
            $holiday = $this->holidayService->getHoliday($todayDate);
            $holidayName = $holiday ? $holiday->name : 'Hari Libur';
            Log::info("Morning reminders skipped: Today ({$todayDate}) is a holiday ({$holidayName}).");
            return [];
        }

        $activeSemester = Semester::where('is_active', true)->first();

        $schedulesQuery = Schedule::query()
            ->where('is_active', true)
            ->where('day', $todayName)
            ->with(['course', 'lecturer', 'coursePic']);

        if ($activeSemester) {
            $schedulesQuery->whereHas('course', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $schedules = $schedulesQuery->get();
        $results = [];

        foreach ($schedules as $schedule) {
            $pic = $schedule->coursePic;
            $lecturer = $schedule->lecturer;
            $course = $schedule->course;

            if (!$pic || !$pic->phone_number) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'course' => $course->name ?? '-',
                    'status' => 'skipped',
                    'reason' => 'No Course PIC or phone number assigned',
                ];
                continue;
            }

            // Check if already sent today
            $alreadySent = ReminderLog::where('schedule_id', $schedule->id)
                ->where('type', 'morning')
                ->where('reminder_date', $todayDate)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent && !$dryRun) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'course' => $course->name,
                    'status' => 'skipped',
                    'reason' => 'Already sent today',
                ];
                continue;
            }

            $lecturerSalutation = $lecturer->salutation ?? 'Bapak/Ibu';
            $lecturerName = $lecturer->name ?? 'Dosen Pengampu';
            $lecturerPhone = $lecturer->phone_number ?? '-';
            $startTime = substr($schedule->start_time, 0, 5);
            $endTime = substr($schedule->end_time, 0, 5);
            $room = $schedule->room ?: 'Ruang Kuliah';

            $message = "Selamat pagi {$pic->name},\n\n"
                . "Jangan lupa untuk menghubungi {$lecturerSalutation} {$lecturerName} bahwa hari ini akan ada mata kuliah:\n\n"
                . "📚 {$course->name}\n"
                . "⏰ Pukul: {$startTime} - {$endTime} WIB\n"
                // . "🏛️ Ruangan: {$room}\n"
                . "📞 Nomor Dosen: {$lecturerPhone}\n\n"
                . "Jangan lupa ya!\n\n"
                . "— Pesan ini dikirim otomatis.";

            if ($dryRun) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'pic' => $pic->name,
                    'target_phone' => $pic->phone_number,
                    'status' => 'dry_run',
                    'message' => $message,
                ];
                continue;
            }

            $sendResult = $this->fonnte->sendMessage($pic->phone_number, $message);
            $status = $sendResult['status'] === 'sent' ? 'sent' : 'failed';

            ReminderLog::create([
                'schedule_id' => $schedule->id,
                'type' => 'morning',
                'reminder_date' => $todayDate,
                'target_phone' => $pic->phone_number,
                'status' => $status,
                'message' => $message,
                'response_payload' => $sendResult['data'] ?? ['error' => $sendResult['message']],
                'sent_at' => $status === 'sent' ? now() : null,
            ]);

            $results[] = [
                'schedule_id' => $schedule->id,
                'pic' => $pic->name,
                'target_phone' => $pic->phone_number,
                'status' => $status,
                'response' => $sendResult,
            ];
        }

        return $results;
    }

    /**
     * Send pre-class reminder (approx 1 hour before class starts)
     */
    public function sendPreclassReminders(bool $dryRun = false, bool $ignoreHoliday = false): array
    {
        $todayName = strtolower(now()->format('l'));
        $todayDate = now()->toDateString();
        $currentTime = Carbon::now();

        if (!$ignoreHoliday && $this->holidayService->isHoliday($todayDate)) {
            $holiday = $this->holidayService->getHoliday($todayDate);
            $holidayName = $holiday ? $holiday->name : 'Hari Libur';
            Log::info("Pre-class reminders skipped: Today ({$todayDate}) is a holiday ({$holidayName}).");
            return [];
        }

        $activeSemester = Semester::where('is_active', true)->first();

        $schedulesQuery = Schedule::query()
            ->where('is_active', true)
            ->where('day', $todayName)
            ->with(['course', 'lecturer', 'coursePic']);

        if ($activeSemester) {
            $schedulesQuery->whereHas('course', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $schedules = $schedulesQuery->get();
        $results = [];

        foreach ($schedules as $schedule) {
            $pic = $schedule->coursePic;
            $lecturer = $schedule->lecturer;
            $course = $schedule->course;

            if (!$pic || !$pic->phone_number) {
                continue;
            }

            // Calculate start datetime for today
            $classStartTime = Carbon::createFromTimeString($schedule->start_time);
            $diffInMinutes = $currentTime->diffInMinutes($classStartTime, false);

            // Trigger when class starts in 50 to 65 minutes
            if (!$dryRun && ($diffInMinutes < 50 || $diffInMinutes > 65)) {
                continue;
            }

            // Check if already sent today
            $alreadySent = ReminderLog::where('schedule_id', $schedule->id)
                ->where('type', 'pre_class')
                ->where('reminder_date', $todayDate)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent && !$dryRun) {
                continue;
            }

            $lecturerSalutation = $lecturer->salutation ?? 'Bapak/Ibu';
            $lecturerName = $lecturer->name ?? 'Dosen Pengampu';
            $lecturerPhone = $lecturer->phone_number ?? '-';
            $startTime = substr($schedule->start_time, 0, 5);
            $room = $schedule->room ?: 'Ruang Kuliah';

            $message = "Halo {$pic->name},\n\n"
                . "🔔 Pengingat: 1 jam lagi (pukul {$startTime} WIB) mata kuliah {$course->name} akan dimulai.\n"
                . "Pastikan sudah mengabari {$lecturerSalutation} {$lecturerName} ({$lecturerPhone}) ya!\n\n"
                // . "Semangat perkuliahannya! 🙌\n\n"
                . "— Pesan ini dikirim otomatis.";

            if ($dryRun) {
                $results[] = [
                    'schedule_id' => $schedule->id,
                    'pic' => $pic->name,
                    'target_phone' => $pic->phone_number,
                    'status' => 'dry_run',
                    'diff_minutes' => $diffInMinutes,
                    'message' => $message,
                ];
                continue;
            }

            $sendResult = $this->fonnte->sendMessage($pic->phone_number, $message);
            $status = $sendResult['status'] === 'sent' ? 'sent' : 'failed';

            ReminderLog::create([
                'schedule_id' => $schedule->id,
                'type' => 'pre_class',
                'reminder_date' => $todayDate,
                'target_phone' => $pic->phone_number,
                'status' => $status,
                'message' => $message,
                'response_payload' => $sendResult['data'] ?? ['error' => $sendResult['message']],
                'sent_at' => $status === 'sent' ? now() : null,
            ]);

            $results[] = [
                'schedule_id' => $schedule->id,
                'pic' => $pic->name,
                'target_phone' => $pic->phone_number,
                'status' => $status,
                'response' => $sendResult,
            ];
        }

        return $results;
    }

    /**
     * Format list of tasks into neat WhatsApp markdown message
     */
    public function formatTasksListMessage($tasks, bool $isReminder = false): string
    {
        if ($tasks->isEmpty()) {
            return "🎉 *TIDAK ADA TUGAS*\n\n"
                . "Yeay!, saat ini belum ada tugas/PR yang tercatat aktif! Tetap semangat ya!";
        }

        $todayDateStr = Carbon::now()->locale('id')->translatedFormat('l, d F Y');

        $headerTitle = $isReminder ? 'REMINDER TUGAS KELAS 1E-SIB' : 'Daftar tugas kelas 1E-SIB';
        $subHeader = $isReminder ? "⏰ _Pengingat Otomatis Harian (Pukul 18:00 WIB)_\n" : "";

        $message = "📋 *{$headerTitle}*\n"
            . $subHeader
            . "📅 _{$todayDateStr}_\n"
            . "━━━━━━━━━━━━━━━━━━━━\n";

        $grouped = $tasks->groupBy(function ($task) {
            return $task->course ? $task->course->name : 'Lainnya';
        });

        $courseIndex = 0;
        foreach ($grouped as $courseName => $courseTasks) {
            if ($courseIndex > 0) {
                $message .= "\n────────────────────\n";
            }

            $message .= "\n📚 *{$courseName}*\n";

            $taskIndex = 1;
            foreach ($courseTasks as $task) {
                $description = trim($task->description ?? '');

                $deadlineStr = '-';
                $statusBadge = '';
                if ($task->due_date) {
                    $dueDate = Carbon::parse($task->due_date);
                    $deadlineStr = $dueDate->locale('id')->translatedFormat('d M Y, H:i') . ' WIB';

                    $now = Carbon::now();
                    if ($dueDate->isPast()) {
                        $statusBadge = ' ⚠️ _(Lewat)_';
                    } elseif ($dueDate->isToday()) {
                        $statusBadge = ' 🔥 _(Hari Ini!)_';
                    } elseif ($dueDate->isTomorrow()) {
                        $statusBadge = ' ⏳ _(Besok)_';
                    } else {
                        $diffInDays = (int) ceil($now->diffInDays($dueDate, false));
                        if ($diffInDays > 0 && $diffInDays <= 7) {
                            $statusBadge = " 🗓️ _({$diffInDays} hari lagi)_";
                        }
                    }
                }

                $message .= "\n{$taskIndex}. *{$task->title}*\n"
                    . "   • *Deadline:* {$deadlineStr}{$statusBadge}\n";

                if (!empty($description) && $description !== '-') {
                    $message .= "   • *Deskripsi:* {$description}\n";
                }

                $taskIndex++;
            }

            $courseIndex++;
        }

        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n"
            . "💡 _Yuk cicil tugasnya dari sekarang biar tidak menumpuk!_";

        return trim($message);
    }

    /**
     * Send daily task reminder (at 18:00 WIB) to the class WhatsApp group
     */
    public function sendDailyTaskReminder(bool $dryRun = false, ?string $targetGroup = null, bool $force = false, bool $skipIfEmpty = false): array
    {
        $group = $targetGroup ?: config('services.fonnte.allowed_group_id');

        if (empty($group)) {
            Log::warning('Daily task reminder skipped: No WhatsApp group configured in services.fonnte.allowed_group_id');
            return [
                'status' => 'skipped',
                'reason' => 'No WhatsApp group ID configured',
            ];
        }

        $todayDate = now()->toDateString();

        // Check if already sent today (unless forced or in dry run)
        if (!$force && !$dryRun) {
            $alreadySent = ReminderLog::where('type', 'daily_task')
                ->where('reminder_date', $todayDate)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                Log::info("Daily task reminder skipped: Already sent for date {$todayDate}");
                return [
                    'status' => 'skipped',
                    'group' => $group,
                    'reason' => 'Already sent today',
                ];
            }
        }

        $activeSemester = Semester::where('is_active', true)->first();

        $query = Task::pending()->with(['course']);

        if ($activeSemester) {
            $query->whereHas('course', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $tasks = $query->orderBy('due_date', 'asc')->get();

        if ($tasks->isEmpty() && $skipIfEmpty) {
            Log::info("Daily task reminder skipped: No pending tasks and skipIfEmpty is true.");
            return [
                'status' => 'skipped',
                'group' => $group,
                'reason' => 'No pending tasks to remind',
            ];
        }

        $message = $this->formatTasksListMessage($tasks, true);

        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'group' => $group,
                'target_date' => $todayDate,
                'task_count' => $tasks->count(),
                'message' => $message,
            ];
        }

        $sendResult = $this->fonnte->sendMessage($group, $message);
        $status = ($sendResult['status'] ?? '') === 'sent' ? 'sent' : 'failed';

        ReminderLog::create([
            'schedule_id' => null,
            'type' => 'daily_task',
            'reminder_date' => $todayDate,
            'target_phone' => $group,
            'status' => $status,
            'message' => $message,
            'response_payload' => $sendResult['data'] ?? ['error' => $sendResult['message'] ?? 'Unknown error'],
            'sent_at' => $status === 'sent' ? now() : null,
        ]);

        return [
            'status' => $status,
            'group' => $group,
            'target_date' => $todayDate,
            'task_count' => $tasks->count(),
            'message' => $message,
            'response' => $sendResult,
        ];
    }
}
