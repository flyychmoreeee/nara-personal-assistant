<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class HolidayController extends Controller
{
    protected HolidayService $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
    }

    #[OA\Get(
        path: '/api/holidays',
        operationId: 'getHolidaysList',
        tags: ['Holidays'],
        summary: 'Get list of holidays',
        description: 'Returns list of all holidays with optional filters',
        parameters: [
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2026)),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['national', 'academic', 'custom'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query();

        if ($request->has('year')) {
            $year = (int) $request->query('year');
            $query->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        $holidays = $query->orderBy('start_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $holidays,
        ]);
    }

    #[OA\Post(
        path: '/api/holidays',
        operationId: 'storeHoliday',
        tags: ['Holidays'],
        summary: 'Create new holiday or holiday range',
        description: 'Create a single-day holiday or a bulk date-range holiday (e.g. semester break)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'start_date'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Libur Perpindahan Semester Ganjil'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-07-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', description: 'Defaults to start_date if omitted', example: '2026-08-31'),
                    new OA\Property(property: 'type', type: 'string', enum: ['national', 'academic', 'custom'], example: 'academic'),
                    new OA\Property(property: 'description', type: 'string', example: 'Libur semester genap ke ganjil'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Holiday created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'type' => 'nullable|string|in:national,academic,custom',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $holiday = Holiday::create([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? $validated['start_date'],
            'type' => $validated['type'] ?? 'custom',
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan.',
            'data' => $holiday,
        ], 201);
    }

    #[OA\Get(
        path: '/api/holidays/check',
        operationId: 'checkHoliday',
        tags: ['Holidays'],
        summary: 'Check if today or specific date is a holiday',
        description: 'Checks whether a date is within an active holiday',
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Date in Y-m-d format. Defaults to today.', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-08-25')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful check')
        ]
    )]
    public function check(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $isHoliday = $this->holidayService->isHoliday($date);
        $holidayDetails = $isHoliday ? $this->holidayService->getHoliday($date) : null;

        return response()->json([
            'success' => true,
            'date' => $date,
            'is_holiday' => $isHoliday,
            'holiday' => $holidayDetails,
        ]);
    }

    #[OA\Post(
        path: '/api/holidays/sync',
        operationId: 'syncNationalHolidays',
        tags: ['Holidays'],
        summary: 'Sync Indonesian national holidays',
        description: 'Fetch and save national holidays from public API / package into database',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'year', type: 'integer', example: 2026),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Holidays synced successfully')
        ]
    )]
    public function sync(Request $request): JsonResponse
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $synced = $this->holidayService->syncNationalHolidays($year);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil sinkronisasi ' . count($synced) . " hari libur nasional untuk tahun {$year}.",
            'synced_count' => count($synced),
            'data' => $synced,
        ]);
    }

    #[OA\Get(
        path: '/api/holidays/{id}',
        operationId: 'getHolidayById',
        tags: ['Holidays'],
        summary: 'Get holiday by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Holiday not found')
        ]
    )]
    public function show(Holiday $holiday): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $holiday,
        ]);
    }

    #[OA\Put(
        path: '/api/holidays/{id}',
        operationId: 'updateHoliday',
        tags: ['Holidays'],
        summary: 'Update holiday',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Libur Diperpanjang'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-07-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-09-07'),
                    new OA\Property(property: 'type', type: 'string', enum: ['national', 'academic', 'custom']),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Holiday updated successfully'),
            new OA\Response(response: 404, description: 'Holiday not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'type' => 'nullable|string|in:national,academic,custom',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['start_date']) && !isset($validated['end_date']) && !$holiday->end_date) {
            $validated['end_date'] = $validated['start_date'];
        }

        $holiday->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil diperbarui.',
            'data' => $holiday,
        ]);
    }

    #[OA\Delete(
        path: '/api/holidays/{id}',
        operationId: 'deleteHoliday',
        tags: ['Holidays'],
        summary: 'Delete holiday',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Holiday deleted successfully'),
            new OA\Response(response: 404, description: 'Holiday not found')
        ]
    )]
    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus.',
        ]);
    }
}
