<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KasExpense;
use App\Models\KasPayment;
use App\Models\KasWeek;
use App\Models\Student;
use App\Services\KasSpreadsheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KasPaymentController extends Controller
{
    #[OA\Post(
        path: '/api/kas/toggle-payment',
        operationId: 'toggleKasPayment',
        tags: ['Kas - Payments'],
        summary: 'One-Tap Toggle Payment Status',
        description: 'Toggles a student payment status between paid and unpaid for a specific week, or sets payment method',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['student_id', 'kas_week_id'],
                properties: [
                    new OA\Property(property: 'student_id', type: 'integer', example: 1),
                    new OA\Property(property: 'kas_week_id', type: 'integer', example: 1),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['cash', 'qris'], default: 'cash', example: 'cash'),
                    new OA\Property(property: 'status', type: 'string', enum: ['paid', 'unpaid'], nullable: true, example: 'paid'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Bayar tunai'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Payment status updated successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function togglePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'kas_week_id' => 'required|exists:kas_weeks,id',
            'payment_method' => 'nullable|string|in:cash,qris',
            'status' => 'nullable|string|in:paid,unpaid',
            'notes' => 'nullable|string|max:255',
        ]);

        $week = KasWeek::findOrFail($validated['kas_week_id']);
        $payment = KasPayment::where('student_id', $validated['student_id'])
            ->where('kas_week_id', $validated['kas_week_id'])
            ->first();

        $desiredStatus = $validated['status'] ?? null;
        $paymentMethod = $validated['payment_method'] ?? null;

        if ($payment) {
            $newStatus = $desiredStatus ?? ($payment->status === 'paid' ? 'unpaid' : 'paid');

            if ($newStatus === 'unpaid') {
                $payment->update([
                    'status' => 'unpaid',
                    'paid_at' => null,
                ]);
                $message = 'Pembayaran dibatalkan (Belum Bayar)';
            } else {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => $payment->paid_at ?? now(),
                    'amount_paid' => $week->amount,
                    'payment_method' => $paymentMethod ?? $payment->payment_method ?? KasPayment::METHOD_CASH,
                    'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $payment->notes,
                ]);
                $message = 'Pembayaran berhasil dicatat (Lunas)';
            }
        } else {
            $isPaid = ($desiredStatus ?? 'paid') === 'paid';
            $payment = KasPayment::create([
                'student_id' => $validated['student_id'],
                'kas_week_id' => $validated['kas_week_id'],
                'amount_paid' => $isPaid ? $week->amount : 0,
                'status' => $isPaid ? 'paid' : 'unpaid',
                'paid_at' => $isPaid ? now() : null,
                'payment_method' => $paymentMethod ?? KasPayment::METHOD_CASH,
                'notes' => $validated['notes'] ?? null,
            ]);
            $message = $isPaid ? 'Pembayaran berhasil dicatat (Lunas)' : 'Status pembayaran disetel Belum Bayar';
        }

        app(KasSpreadsheetService::class)->syncQuietly();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'student_id' => $payment->student_id,
                'kas_week_id' => $payment->kas_week_id,
                'status' => $payment->status,
                'is_paid' => $payment->status === 'paid',
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at,
                'amount_paid' => $payment->amount_paid,
                'notes' => $payment->notes,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/kas/weeks/{weekId}/students',
        operationId: 'getWeekStudentsPaymentStatus',
        tags: ['Kas - Payments'],
        summary: 'Get students checklist for a week',
        description: 'Returns all active students with their payment status for treasurer one-tap interface',
        parameters: [
            new OA\Parameter(name: 'weekId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Week not found')
        ]
    )]
    public function weekStudents(Request $request, int $weekId): JsonResponse
    {
        $week = KasWeek::find($weekId);

        if (!$week) {
            return response()->json([
                'success' => false,
                'message' => 'Kas week not found',
            ], 404);
        }

        $query = Student::where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('attendance_number', $search);
            });
        }

        $students = $query->orderBy('attendance_number', 'asc')->get();

        // Get all payments for this week
        $payments = KasPayment::where('kas_week_id', $weekId)
            ->get()
            ->keyBy('student_id');

        $studentList = $students->map(function ($student) use ($payments, $week) {
            $payment = $payments->get($student->id);
            $isPaid = $payment && $payment->status === 'paid';

            return [
                'id' => $student->id,
                'attendance_number' => $student->attendance_number,
                'name' => $student->name,
                'nickname' => $student->nickname,
                'is_paid' => $isPaid,
                'status' => $isPaid ? 'paid' : 'unpaid',
                'payment_method' => $isPaid ? ($payment->payment_method ?? KasPayment::METHOD_CASH) : null,
                'amount_paid' => $isPaid ? $payment->amount_paid : 0,
                'paid_at' => $isPaid ? $payment->paid_at : null,
                'notes' => $payment ? $payment->notes : null,
            ];
        });

        $totalStudents = $students->count();
        $paidStudents = $studentList->where('is_paid', true);
        $paidCount = $paidStudents->count();
        $unpaidCount = $totalStudents - $paidCount;
        $totalCollected = $paidCount * $week->amount;
        $targetAmount = $totalStudents * $week->amount;

        $cashCount = $paidStudents->where('payment_method', KasPayment::METHOD_CASH)->count();
        $qrisCount = $paidStudents->where('payment_method', KasPayment::METHOD_QRIS)->count();
        $totalCash = $cashCount * $week->amount;
        $totalQris = $qrisCount * $week->amount;

        return response()->json([
            'success' => true,
            'week' => $week,
            'stats' => [
                'total_students' => $totalStudents,
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
                'cash_count' => $cashCount,
                'qris_count' => $qrisCount,
                'total_collected' => $totalCollected,
                'total_cash' => $totalCash,
                'total_qris' => $totalQris,
                'target_amount' => $targetAmount,
                'collection_percentage' => $targetAmount > 0 ? round(($totalCollected / $targetAmount) * 100, 1) : 0,
            ],
            'data' => $studentList,
        ]);
    }

    #[OA\Get(
        path: '/api/kas/overview',
        operationId: 'getKasOverview',
        tags: ['Kas - Reports'],
        summary: 'Get kas financial overview & summary',
        description: 'Returns total balance, total income, total expenses, active week, and recent activity',
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function overview(): JsonResponse
    {
        $totalIncome = (int) KasPayment::where('status', 'paid')->sum('amount_paid');
        $totalCashIncome = (int) KasPayment::where('status', 'paid')->where('payment_method', KasPayment::METHOD_CASH)->sum('amount_paid');
        $totalQrisIncome = (int) KasPayment::where('status', 'paid')->where('payment_method', KasPayment::METHOD_QRIS)->sum('amount_paid');
        $totalExpense = (int) KasExpense::sum('amount');
        $balance = $totalIncome - $totalExpense;

        $activeWeek = KasWeek::where('type', 'routine')->where('is_active', true)->first()
            ?? KasWeek::where('type', 'routine')->orderBy('year', 'desc')->orderBy('month', 'desc')->orderBy('week', 'desc')->first();

        $activeWeekStats = null;
        if ($activeWeek) {
            $totalActiveStudents = Student::where('is_active', true)->count();
            $paidPayments = KasPayment::where('kas_week_id', $activeWeek->id)->where('status', 'paid')->get();
            $paidCount = $paidPayments->count();
            $cashCount = $paidPayments->where('payment_method', KasPayment::METHOD_CASH)->count();
            $qrisCount = $paidPayments->where('payment_method', KasPayment::METHOD_QRIS)->count();

            $activeWeekStats = [
                'id' => $activeWeek->id,
                'title' => $activeWeek->title,
                'amount' => $activeWeek->amount,
                'total_students' => $totalActiveStudents,
                'paid_count' => $paidCount,
                'unpaid_count' => max(0, $totalActiveStudents - $paidCount),
                'cash_count' => $cashCount,
                'qris_count' => $qrisCount,
                'collected' => $paidCount * $activeWeek->amount,
                'collected_cash' => $cashCount * $activeWeek->amount,
                'collected_qris' => $qrisCount * $activeWeek->amount,
            ];
        }

        $recentExpenses = KasExpense::orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balance,
                'total_income' => $totalIncome,
                'total_cash_income' => $totalCashIncome,
                'total_qris_income' => $totalQrisIncome,
                'total_expense' => $totalExpense,
                'active_week' => $activeWeekStats,
                'recent_expenses' => $recentExpenses,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/kas/matrix',
        operationId: 'getKasMatrix',
        tags: ['Kas - Reports'],
        summary: 'Get multi-week student payment matrix',
        description: 'Returns all students with their payment status across recent weeks (for public transparency table)',
        parameters: [
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit_weeks', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 8)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function matrix(Request $request): JsonResponse
    {
        $weeksQuery = KasWeek::query();

        if ($request->filled('type')) {
            $weeksQuery->where('type', $request->query('type'));
        }

        if ($request->filled('year')) {
            $year = $request->query('year');
            $weeksQuery->where(function ($q) use ($year) {
                $q->where('year', $year)->orWhereNull('year');
            });
        }
        if ($request->filled('month')) {
            $month = $request->query('month');
            $weeksQuery->where(function ($q) use ($month) {
                $q->where('month', $month)->orWhereNull('month');
            });
        }

        $limitWeeks = $request->integer('limit_weeks', 25);
        $weeks = $weeksQuery->orderByRaw('COALESCE(year, 0) asc')
            ->orderByRaw('COALESCE(month, 0) asc')
            ->orderByRaw('COALESCE(week, 0) asc')
            ->orderBy('id', 'asc')
            ->take($limitWeeks)
            ->get();

        $weekIds = $weeks->pluck('id');

        $students = Student::where('is_active', true)
            ->orderBy('attendance_number', 'asc')
            ->get();

        $payments = KasPayment::whereIn('kas_week_id', $weekIds)
            ->where('status', 'paid')
            ->get();

        // Group payments by student_id and kas_week_id
        $paymentMap = [];
        foreach ($payments as $p) {
            $paymentMap[$p->student_id][$p->kas_week_id] = [
                'is_paid' => true,
                'payment_method' => $p->payment_method ?? KasPayment::METHOD_CASH,
            ];
        }

        $matrix = $students->map(function ($student) use ($weeks, $paymentMap) {
            $weekStatuses = [];
            $unpaidCount = 0;
            $totalArrears = 0;

            foreach ($weeks as $w) {
                $paymentInfo = $paymentMap[$student->id][$w->id] ?? null;
                $isPaid = !is_null($paymentInfo);
                $weekStatuses[] = [
                    'week_id' => $w->id,
                    'week_title' => $w->title,
                    'is_paid' => $isPaid,
                    'payment_method' => $isPaid ? ($paymentInfo['payment_method'] ?? KasPayment::METHOD_CASH) : null,
                ];

                if (!$isPaid) {
                    $unpaidCount++;
                    $totalArrears += $w->amount;
                }
            }

            return [
                'id' => $student->id,
                'attendance_number' => $student->attendance_number,
                'name' => $student->name,
                'nickname' => $student->nickname,
                'weeks' => $weekStatuses,
                'unpaid_weeks_count' => $unpaidCount,
                'total_arrears' => $totalArrears,
            ];
        });

        return response()->json([
            'success' => true,
            'weeks' => $weeks,
            'students' => $matrix,
        ]);
    }
}
