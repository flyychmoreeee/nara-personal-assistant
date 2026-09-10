<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderLog;
use App\Services\FonnteService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReminderController extends Controller
{
    protected ReminderService $reminderService;
    protected FonnteService $fonnteService;

    public function __construct(ReminderService $reminderService, FonnteService $fonnteService)
    {
        $this->reminderService = $reminderService;
        $this->fonnteService = $fonnteService;
    }

    #[OA\Post(
        path: '/api/reminders/test-whatsapp',
        operationId: 'testWhatsAppMessage',
        tags: ['Reminders'],
        summary: 'Test WhatsApp message delivery via Fonnte',
        description: 'Sends a test WhatsApp message to verify Fonnte gateway configuration',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone_number'],
                properties: [
                    new OA\Property(property: 'phone_number', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'message', type: 'string', example: 'Halo! Ini adalah pesan pengujian WhatsApp dari NARA Assistant.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Message processed'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function testWhatsApp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:30',
            'message' => 'nullable|string',
        ]);

        $message = $validated['message'] ?? "Halo! Ini adalah pesan pengujian WhatsApp dari NARA Assistant. Sistem berjalan normal! 🚀";

        $response = $this->fonnteService->sendMessage($validated['phone_number'], $message);

        return response()->json([
            'success' => $response['success'],
            'message' => $response['message'],
            'data' => $response['data'],
        ]);
    }

    #[OA\Post(
        path: '/api/reminders/trigger-d-minus-one',
        operationId: 'triggerDMinusOneReminder',
        tags: ['Reminders'],
        summary: 'Manually trigger H-1 Afternoon Reminders (16:00 WIB)',
        description: 'Triggers the 16:00 WIB afternoon reminder for tomorrow\'s classes (or a specific overridden day)',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'dry_run', type: 'boolean', example: false),
                    new OA\Property(property: 'ignore_holiday', type: 'boolean', example: false),
                    new OA\Property(
                        property: 'day',
                        type: 'string',
                        enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        example: 'tuesday'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'H-1 Reminders triggered')
        ]
    )]
    public function triggerDMinusOne(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);
        $ignoreHoliday = $request->boolean('ignore_holiday', false);
        $day = $request->input('day');

        $results = $this->reminderService->sendDMinusOneReminders($dryRun, $day, $ignoreHoliday);

        return response()->json([
            'success' => true,
            'message' => 'H-1 afternoon reminders processed',
            'total' => count($results),
            'data' => $results,
        ]);
    }

    #[OA\Post(
        path: '/api/reminders/trigger-morning',
        operationId: 'triggerMorningReminder',
        tags: ['Reminders'],
        summary: 'Manually trigger Morning Reminders',
        description: 'Triggers the 05:00 AM morning reminder for today\'s classes (or a specific overridden day)',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'dry_run', type: 'boolean', example: false),
                    new OA\Property(property: 'ignore_holiday', type: 'boolean', example: false),
                    new OA\Property(
                        property: 'day',
                        type: 'string',
                        enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        example: 'monday'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reminders triggered')
        ]
    )]
    public function triggerMorning(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);
        $ignoreHoliday = $request->boolean('ignore_holiday', false);
        $day = $request->input('day');

        $results = $this->reminderService->sendMorningReminders($dryRun, $day, $ignoreHoliday);

        return response()->json([
            'success' => true,
            'message' => 'Morning reminders processed',
            'total' => count($results),
            'data' => $results,
        ]);
    }

    #[OA\Post(
        path: '/api/reminders/trigger-preclass',
        operationId: 'triggerPreclassReminder',
        tags: ['Reminders'],
        summary: 'Manually trigger Pre-Class Reminders (H-1 Hour)',
        description: 'Triggers the 1-hour pre-class reminder check',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'dry_run', type: 'boolean', example: false),
                    new OA\Property(property: 'ignore_holiday', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reminders triggered')
        ]
    )]
    public function triggerPreclass(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);
        $ignoreHoliday = $request->boolean('ignore_holiday', false);

        $results = $this->reminderService->sendPreclassReminders($dryRun, $ignoreHoliday);

        return response()->json([
            'success' => true,
            'message' => 'Pre-class reminders processed',
            'total' => count($results),
            'data' => $results,
        ]);
    }

    #[OA\Get(
        path: '/api/reminders/logs',
        operationId: 'getReminderLogs',
        tags: ['Reminders'],
        summary: 'Get reminder delivery logs',
        description: 'Returns delivery history and status of automated WhatsApp reminders',
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['morning', 'pre_class'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['sent', 'failed', 'pending'])),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function logs(Request $request): JsonResponse
    {
        $query = ReminderLog::with(['schedule.course', 'schedule.lecturer', 'schedule.coursePic'])
            ->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->where('reminder_date', $request->query('date'));
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
