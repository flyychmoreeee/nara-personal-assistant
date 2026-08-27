<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppCommandHandler
{
    protected FonnteService $fonnte;
    protected HolidayService $holidayService;

    public function __construct(FonnteService $fonnte, HolidayService $holidayService)
    {
        $this->fonnte = $fonnte;
        $this->holidayService = $holidayService;
    }

    /**
     * Handle incoming WhatsApp message payload from Fonnte
     */
    public function handle(array $payload): array
    {
        $messageText = trim($payload['message'] ?? '');
        $sender = trim($payload['sender'] ?? ($payload['from'] ?? ''));
        $senderName = trim($payload['name'] ?? 'Teman');

        if (empty($messageText) || empty($sender)) {
            return [
                'handled' => false,
                'reason' => 'Empty message or sender',
            ];
        }

        // Check if message is a command (starts with ! or /)
        if (!str_starts_with($messageText, '!') && !str_starts_with($messageText, '/')) {
            return [
                'handled' => false,
                'reason' => 'Not a command trigger',
            ];
        }

        Log::info("WhatsApp raw command received", [
            'sender' => $sender,
            'message' => $messageText,
            'member' => $payload['member'] ?? null,
            'name' => $senderName,
            'full_payload' => $payload,
        ]);

        // Validate allowed WhatsApp Group (flexible match with or without @g.us)
        $allowedGroup = config('services.fonnte.allowed_group_id');
        if (!empty($allowedGroup)) {
            $cleanAllowed = str_replace('@g.us', '', trim($allowedGroup));
            $cleanSender = str_replace('@g.us', '', trim($sender));
            $cleanFrom = str_replace('@g.us', '', trim($payload['from'] ?? ''));

            $isMatch = ($cleanSender === $cleanAllowed) || ($cleanFrom === $cleanAllowed) || str_contains($sender, $cleanAllowed);

            if (!$isMatch) {
                Log::info("WhatsApp command ignored: Sender {$sender} does not match allowed group {$allowedGroup}");
                return [
                    'handled' => false,
                    'reason' => "Sender {$sender} is not the authorized group",
                ];
            }
        }

        // Daily Rate Limiting per group/chat
        $today = now()->format('Y-m-d');
        $cacheKey = "whatsapp_cmd_limit:{$sender}:{$today}";
        $dailyLimit = (int) config('services.fonnte.daily_command_limit', 10);
        $currentCount = (int) Cache::get($cacheKey, 0);

        if ($currentCount >= $dailyLimit) {
            $quotaMessage = "⚠️ *Kuota Command Harian Habis*\n\n"
                . "Maaf {$senderName}, kuota penggunaan command bot untuk grup ini sudah mencapai batas harian ({$dailyLimit}/{$dailyLimit}).\n\n"
                . "Command akan dapat digunakan kembali besok! 🙏";

            $this->fonnte->sendMessage($sender, $quotaMessage);

            return [
                'handled' => true,
                'status' => 'rate_limited',
                'message' => 'Daily limit exceeded',
            ];
        }

        // Clean command string
        $normalizedCommand = strtolower(trim(preg_replace('/\s+/', ' ', $messageText)));

        $responseMessage = match (true) {
            $normalizedCommand === '!tugas' || $normalizedCommand === '/tugas' => $this->handleTasksCommand(),
            $normalizedCommand === '!jadwal' || $normalizedCommand === '/jadwal' || $normalizedCommand === '!jadwal hari ini' || $normalizedCommand === '/jadwal hari ini' => $this->handleScheduleCommand('today'),
            $normalizedCommand === '!jadwal besok' || $normalizedCommand === '/jadwal besok' => $this->handleScheduleCommand('tomorrow'),
            $normalizedCommand === '!dosen' || $normalizedCommand === '/dosen' || $normalizedCommand === '!pic' || $normalizedCommand === '/pic' => $this->handleLecturersCommand(),
            $normalizedCommand === '!help' || $normalizedCommand === '/help' || $normalizedCommand === '!menu' || $normalizedCommand === '/menu' => $this->handleHelpCommand($currentCount + 1, $dailyLimit),
            default => null,
        };

        if ($responseMessage === null) {
            return [
                'handled' => false,
                'reason' => 'Unknown command',
            ];
        }

        // Increment rate limit counter
        Cache::put($cacheKey, $currentCount + 1, now()->endOfDay());

        $sendResult = $this->fonnte->sendMessage($sender, $responseMessage);

        return [
            'handled' => true,
            'status' => $sendResult['status'] ?? 'sent',
            'command' => $normalizedCommand,
            'reply_message' => $responseMessage,
            'response' => $sendResult,
        ];
    }

    /**
     * Handle !tugas command
     */
    public function handleTasksCommand(): string
    {
        $activeSemester = Semester::where('is_active', true)->first();

        $query = Task::pending()->with(['course']);

        if ($activeSemester) {
            $query->whereHas('course', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $tasks = $query->orderBy('due_date', 'asc')->get();

        if ($tasks->isEmpty()) {
            return "🎉 *TIDAK ADA TUGAS*\n\n"
                . "Yeay!, saat ini belum ada tugas/PR yang tercatat aktif! Tetap semangat ya!";
        }

        $message = "📝*Daftar tugas kelas 1E-SIB*\n";

        $grouped = $tasks->groupBy(function ($task) {
            return $task->course ? $task->course->name : 'Lainnya';
        });

        foreach ($grouped as $courseName => $courseTasks) {
            $message .= "\n📚*{$courseName}*\n";

            $taskIndex = 1;
            foreach ($courseTasks as $task) {
                $description = $task->description ?: '-';
                $deadline = $task->due_date
                    ? Carbon::parse($task->due_date)->locale('id')->translatedFormat('d M Y, H:i') . ' WIB'
                    : '-';

                $message .= "{$taskIndex}. Judul: {$task->title}\n"
                    . "    Deskripsi: {$description}\n"
                    . "    Deadline: {$deadline}\n";

                $taskIndex++;
            }
        }

        return trim($message);
    }

    /**
     * Handle !jadwal [hari ini|besok] command
     */
    public function handleScheduleCommand(string $dayTarget = 'today'): string
    {
        $date = $dayTarget === 'tomorrow' ? now()->addDay() : now();
        $dayNameEn = strtolower($date->format('l'));
        $formattedDate = $date->locale('id')->translatedFormat('l, d F Y');
        $targetLabel = $dayTarget === 'tomorrow' ? 'BESOK' : 'HARI INI';

        // Check if holiday
        $dateString = $date->toDateString();
        if ($this->holidayService->isHoliday($dateString)) {
            $holiday = $this->holidayService->getHoliday($dateString);
            $holidayName = $holiday ? $holiday->name : 'Hari Libur Nasional';
            return "🏖️ *JADWAL KULIAH {$targetLabel}*\n📅 {$formattedDate}\n\n"
                . "Hari ini libur: *{$holidayName}*.\nTidak ada perkuliahan, selamat beristirahat! 🌴";
        }

        $activeSemester = Semester::where('is_active', true)->first();

        $query = Schedule::query()
            ->where('is_active', true)
            ->where('day', $dayNameEn)
            ->with(['course', 'lecturer', 'coursePic'])
            ->orderBy('start_time', 'asc');

        if ($activeSemester) {
            $query->whereHas('course', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            return "🗓️ *JADWAL KULIAH {$targetLabel}*\n📅 {$formattedDate}\n\n"
                . "Tidak ada jadwal perkuliahan untuk hari ini. Enjoy your day!";
        }

        $targetTitle = $dayTarget === 'tomorrow' ? 'Besok' : 'Hari Ini';
        $message = "🗓️*Jadwal kuliah {$targetTitle}*\n📅 {$formattedDate}\n\n";

        $num = 1;
        foreach ($schedules as $schedule) {
            $course = $schedule->course;
            $lecturer = $schedule->lecturer;
            $pic = $schedule->coursePic;

            $startTime = substr($schedule->start_time, 0, 5);
            $endTime = substr($schedule->end_time, 0, 5);
            $lecturerName = $lecturer ? "{$lecturer->salutation} {$lecturer->name}" : '-';
            $picName = $pic ? "{$pic->name}" : '-';

            $message .= "{$num}. *{$course->name}*\n"
                . "    Pukul: {$startTime} - {$endTime} WIB\n"
                . "    Dosen: {$lecturerName}\n"
                . "    PJ: {$picName}\n\n";

            $num++;
        }

        return trim($message);
    }

    /**
     * Handle !dosen / !pic command
     */
    public function handleLecturersCommand(): string
    {
        $activeSemester = Semester::where('is_active', true)->first();

        $query = Course::query()->with(['schedules.lecturer', 'schedules.coursePic']);

        if ($activeSemester) {
            $query->where('semester_id', $activeSemester->id);
        }

        $courses = $query->orderBy('name', 'asc')->get();

        if ($courses->isEmpty()) {
            return "Belum ada data mata kuliah dan dosen yang terdaftar.";
        }

        $message = "👨‍🏫*Daftar Dosen & PJ Mata Kuliah*\n\n";

        $num = 1;
        foreach ($courses as $course) {
            $schedules = $course->schedules;
            $lecturers = $schedules->map(fn($s) => $s->lecturer)->filter()->unique('id');
            $pics = $schedules->map(fn($s) => $s->coursePic)->filter()->unique('id');

            $lecturerInfo = $lecturers->isNotEmpty()
                ? $lecturers->map(fn($l) => "{$l->salutation} {$l->name} (" . ($l->phone_number ?: '-') . ")")->implode(', ')
                : '-';

            $picInfo = $pics->isNotEmpty()
                ? $pics->map(fn($p) => $p->name)->implode(', ')
                : '-';

            $message .= "{$num}. *{$course->name}*\n"
                . "    Dosen: {$lecturerInfo}\n"
                . "    PJ: {$picInfo}\n\n";

            $num++;
        }

        return trim($message);
    }

    /**
     * Handle !help command
     */
    public function handleHelpCommand(int $currentUsage, int $dailyLimit): string
    {
        $remaining = max(0, $dailyLimit - $currentUsage);

        return "*1E-SIB ACADEMIC ASSISTANT*\n\n"
            . "Halo! Berikut daftar perintah yang bisa kamu gunakan:\n\n"
            . "• *!tugas* : Cek daftar tugas aktif per mata kuliah\n"
            . "• *!jadwal* : Cek jadwal perkuliahan hari ini\n"
            . "• *!jadwal besok* : Cek jadwal perkuliahan esok hari\n"
            . "• *!dosen* : Cek daftar kontak dosen & PJ mata kuliah\n"
            . "• *!help* : Menampilkan panduan bot ini\n\n"
            . "*Status Kuota Grup Hari Ini:*\n"
            . "Penggunaan: {$currentUsage}/{$dailyLimit} kali (Sisa: {$remaining}x)\n\n"
            . "—\n_1E-SIB Academic Assistant siap membantu perkuliahan kelasmu!_";
    }
}
