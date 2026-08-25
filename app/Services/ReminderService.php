<?php

namespace App\Services;

use App\Models\ReminderLog;
use App\Models\Schedule;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    protected FonnteService $fonnte;

    public function __construct(FonnteService $fonnte)
    {
        $this->fonnte = $fonnte;
    }

    /**
     * Send morning reminder to all course PICs whose courses are scheduled today
     */
    public function sendMorningReminders(bool $dryRun = false, ?string $customDay = null): array
    {
        $todayName = $customDay ? strtolower($customDay) : strtolower(now()->format('l'));
        $todayDate = now()->toDateString();

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
                . "📚 {$course->name} ({$course->code})\n"
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
    public function sendPreclassReminders(bool $dryRun = false): array
    {
        $todayName = strtolower(now()->format('l'));
        $todayDate = now()->toDateString();
        $currentTime = Carbon::now();

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
}
