<?php

namespace App\Services;

use App\Models\KasExpense;
use App\Models\KasPayment;
use App\Models\KasWeek;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KasSpreadsheetService
{
    public const CACHE_LAST_SYNCED = 'kas_spreadsheet_last_synced_at';
    public const CACHE_LAST_STATUS = 'kas_spreadsheet_last_status';

    /**
     * Build structured payload representing complete class treasury state
     */
    public function buildPayload(): array
    {
        // 1. Calculate overall financial stats
        $totalIncome = (int) KasPayment::where('status', 'paid')->sum('amount_paid');
        $totalCashIncome = (int) KasPayment::where('status', 'paid')
            ->where('payment_method', KasPayment::METHOD_CASH)
            ->sum('amount_paid');
        $totalQrisIncome = (int) KasPayment::where('status', 'paid')
            ->where('payment_method', KasPayment::METHOD_QRIS)
            ->sum('amount_paid');
        $totalExpense = (int) KasExpense::sum('amount');
        $balance = $totalIncome - $totalExpense;

        // Active week
        $activeWeek = KasWeek::where('type', 'routine')->where('is_active', true)->first()
            ?? KasWeek::where('type', 'routine')->orderBy('year', 'desc')->orderBy('month', 'desc')->orderBy('week', 'desc')->first();

        $activeWeekData = null;
        if ($activeWeek) {
            $activeWeekData = [
                'id' => $activeWeek->id,
                'title' => $activeWeek->title,
                'amount' => $activeWeek->amount,
            ];
        }

        // 2. Fetch all expenses
        $expenses = KasExpense::orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item, $index) {
                $rawDate = $item->expense_date;
                $formattedDate = $rawDate ? Carbon::parse($rawDate)->locale('id')->translatedFormat('d/m/Y') : '-';

                return [
                    'no' => $index + 1,
                    'id' => $item->id,
                    'date' => $rawDate,
                    'formatted_date' => $formattedDate,
                    'title' => $item->title,
                    'amount' => (int) $item->amount,
                    'notes' => $item->notes ?: '-',
                    'receipt_url' => $item->receipt_image ?: null,
                ];
            })
            ->values()
            ->toArray();

        // 3. Fetch all weeks in chronological order
        $weeks = KasWeek::orderByRaw('COALESCE(year, 0) asc')
            ->orderByRaw('COALESCE(month, 0) asc')
            ->orderByRaw('COALESCE(week, 0) asc')
            ->orderBy('id', 'asc')
            ->get();

        $weekIds = $weeks->pluck('id')->toArray();

        // 4. Fetch all active students
        $students = Student::where('is_active', true)
            ->orderBy('attendance_number', 'asc')
            ->get();

        // 5. Fetch all paid payments for these weeks
        $payments = KasPayment::whereIn('kas_week_id', $weekIds)
            ->where('status', 'paid')
            ->get();

        // Group payments by [student_id][kas_week_id]
        $paymentMap = [];
        foreach ($payments as $p) {
            $paymentMap[$p->student_id][$p->kas_week_id] = [
                'is_paid' => true,
                'payment_method' => $p->payment_method ?? KasPayment::METHOD_CASH,
                'amount_paid' => (int) $p->amount_paid,
                'paid_at' => $p->paid_at ? $p->paid_at->toDateTimeString() : null,
            ];
        }

        // 6. Build matrix of students with payment status
        $allPaidCount = 0;
        $arrearsStudentsCount = 0;
        $doubleArrearsCount = 0;
        $totalArrearsAllStudents = 0;

        $studentMatrix = $students->map(function ($student) use ($weeks, $paymentMap, &$allPaidCount, &$arrearsStudentsCount, &$doubleArrearsCount, &$totalArrearsAllStudents) {
            $weekStatuses = [];
            $unpaidWeekTitles = [];
            $unpaidCount = 0;
            $totalArrears = 0;

            foreach ($weeks as $w) {
                $paymentInfo = $paymentMap[$student->id][$w->id] ?? null;
                $isPaid = !is_null($paymentInfo);

                $weekStatuses[$w->id] = [
                    'week_id' => $w->id,
                    'is_paid' => $isPaid,
                    'label' => $isPaid ? 'LUNAS' : 'BELUM',
                    'method' => $isPaid ? ($paymentInfo['payment_method'] ?? KasPayment::METHOD_CASH) : null,
                ];

                if (!$isPaid) {
                    $unpaidCount++;
                    $totalArrears += (int) $w->amount;
                    $unpaidWeekTitles[] = $w->title;
                }
            }

            // Determine status label (sesuai istilah lunas, belum, double)
            if ($unpaidCount === 0) {
                $statusLabel = 'LUNAS ✅';
                $allPaidCount++;
            } elseif ($unpaidCount === 1) {
                $statusLabel = 'BELUM BAYAR (1x)';
                $arrearsStudentsCount++;
            } elseif ($unpaidCount === 2) {
                $statusLabel = 'DOUBLE (2x) ⚠️';
                $arrearsStudentsCount++;
                $doubleArrearsCount++;
            } else {
                $statusLabel = "NUNGGAK ({$unpaidCount}x) 🚨";
                $arrearsStudentsCount++;
                $doubleArrearsCount++;
            }

            $totalArrearsAllStudents += $totalArrears;

            return [
                'id' => $student->id,
                'attendance_number' => $student->attendance_number,
                'name' => $student->name,
                'nickname' => $student->nickname ?: $student->name,
                'weeks' => $weekStatuses,
                'unpaid_count' => $unpaidCount,
                'total_arrears' => $totalArrears,
                'status_label' => $statusLabel,
                'unpaid_weeks_text' => !empty($unpaidWeekTitles) ? implode(', ', $unpaidWeekTitles) : '-',
            ];
        })->values()->toArray();

        // 7. Format weeks metadata
        $weeksFormatted = $weeks->map(function ($w) {
            return [
                'id' => $w->id,
                'title' => $w->title,
                'type' => $w->type,
                'amount' => (int) $w->amount,
                'week' => $w->week,
                'month' => $w->month,
                'year' => $w->year,
            ];
        })->values()->toArray();

        return [
            'secret' => config('services.kas_spreadsheet.secret'),
            'generated_at' => now()->locale('id')->translatedFormat('d F Y, H:i') . ' WIB',
            'summary' => [
                'balance' => $balance,
                'total_income' => $totalIncome,
                'total_cash_income' => $totalCashIncome,
                'total_qris_income' => $totalQrisIncome,
                'total_expense' => $totalExpense,
                'total_students' => $students->count(),
                'all_paid_students_count' => $allPaidCount,
                'arrears_students_count' => $arrearsStudentsCount,
                'double_arrears_count' => $doubleArrearsCount,
                'total_arrears_amount' => $totalArrearsAllStudents,
                'active_week' => $activeWeekData,
            ],
            'expenses' => $expenses,
            'weeks' => $weeksFormatted,
            'students' => $studentMatrix,
        ];
    }

    /**
     * Send current kas data to Google Apps Script Webhook
     */
    public function sync(?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?: config('services.kas_spreadsheet.webhook_url');

        if (empty($url)) {
            $msg = 'Google Spreadsheet Webhook URL belum dikonfigurasi di .env (KAS_SPREADSHEET_WEBHOOK_URL)';
            Log::warning("KasSpreadsheet: {$msg}");
            Cache::put(self::CACHE_LAST_STATUS, [
                'success' => false,
                'message' => $msg,
                'time' => now()->toIso8601String(),
            ], now()->addDays(7));

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $payload = $this->buildPayload();

        try {
            // Google Apps Script redirects with 302 on POST response, so allow_redirects ensures we follow through
            $response = Http::withOptions(['allow_redirects' => true])
                ->timeout(35)
                ->asJson()
                ->post($url, $payload);

            $isSuccessful = $response->successful();
            $responseData = $response->json() ?? ['raw' => $response->body()];

            if ($isSuccessful) {
                Cache::put(self::CACHE_LAST_SYNCED, now()->toIso8601String(), now()->addDays(30));
                Cache::put(self::CACHE_LAST_STATUS, [
                    'success' => true,
                    'message' => 'Spreadsheet berhasil disinkronkan',
                    'time' => now()->toIso8601String(),
                    'response' => $responseData,
                ], now()->addDays(7));

                Log::info('KasSpreadsheet: Sync successful', ['response' => $responseData]);

                return [
                    'success' => true,
                    'message' => 'Spreadsheet berhasil disinkronkan',
                    'synced_at' => now()->locale('id')->translatedFormat('d F Y, H:i:s') . ' WIB',
                    'data' => $responseData,
                ];
            }

            $errorMsg = 'Gagal sinkronisasi ke Spreadsheet (HTTP ' . $response->status() . ')';
            Log::error("KasSpreadsheet: {$errorMsg}", ['body' => $response->body()]);

            Cache::put(self::CACHE_LAST_STATUS, [
                'success' => false,
                'message' => $errorMsg,
                'status_code' => $response->status(),
                'time' => now()->toIso8601String(),
            ], now()->addDays(7));

            return [
                'success' => false,
                'message' => $errorMsg,
                'status_code' => $response->status(),
                'error_detail' => $responseData,
            ];
        } catch (\Throwable $e) {
            $errorMsg = 'Koneksi ke Google Spreadsheet gagal: ' . $e->getMessage();
            Log::error("KasSpreadsheet: {$errorMsg}", ['exception' => $e]);

            Cache::put(self::CACHE_LAST_STATUS, [
                'success' => false,
                'message' => $errorMsg,
                'time' => now()->toIso8601String(),
            ], now()->addDays(7));

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }
    }

    /**
     * Dispatch auto sync quietly without blocking or crashing the current request
     */
    public function syncQuietly(): void
    {
        $autoSync = config('services.kas_spreadsheet.auto_sync', true);
        $url = config('services.kas_spreadsheet.webhook_url');

        if (!$autoSync || empty($url)) {
            return;
        }

        // Run after HTTP response has been sent to client to keep user interaction lightning fast
        try {
            dispatch(function () {
                $this->sync();
            })->afterResponse();
        } catch (\Throwable $e) {
            // Fallback: run directly without throwing
            try {
                $this->sync();
            } catch (\Throwable $ignored) {
                Log::warning('KasSpreadsheet: Quiet sync failed', ['error' => $ignored->getMessage()]);
            }
        }
    }

    /**
     * Get current spreadsheet integration status & preview data
     */
    public function getStatus(): array
    {
        $webhookUrl = config('services.kas_spreadsheet.webhook_url');
        $publicUrl = config('services.kas_spreadsheet.public_url');
        $autoSync = (bool) config('services.kas_spreadsheet.auto_sync', true);
        $lastSynced = Cache::get(self::CACHE_LAST_SYNCED);
        $lastStatus = Cache::get(self::CACHE_LAST_STATUS);

        return [
            'is_configured' => !empty($webhookUrl),
            'webhook_url_preview' => $webhookUrl ? substr($webhookUrl, 0, 40) . '...' : null,
            'public_url' => $publicUrl ?: null,
            'auto_sync' => $autoSync,
            'last_synced_at' => $lastSynced ? Carbon::parse($lastSynced)->locale('id')->translatedFormat('d F Y, H:i:s') . ' WIB' : null,
            'last_status' => $lastStatus,
        ];
    }
}
