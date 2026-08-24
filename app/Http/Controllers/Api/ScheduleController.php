<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ScheduleController extends Controller
{
    #[OA\Get(
        path: '/api/schedules',
        operationId: 'getSchedulesList',
        tags: ['Schedules'],
        summary: 'Get list of academic schedules',
        description: 'Returns list of class schedules with filters for day, semester, and active status',
        parameters: [
            new OA\Parameter(
                name: 'day',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
                )
            ),
            new OA\Parameter(name: 'semester_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Schedule::with(['course.semester', 'lecturer', 'coursePic']);

        if ($request->filled('day')) {
            $query->where('day', strtolower($request->query('day')));
        }

        if ($request->filled('semester_id')) {
            $query->whereHas('course', function ($q) use ($request) {
                $q->where('semester_id', $request->query('semester_id'));
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $schedules = $query
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    #[OA\Post(
        path: '/api/schedules',
        operationId: 'storeSchedule',
        tags: ['Schedules'],
        summary: 'Create new schedule',
        description: 'Creates a new academic class schedule',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['course_id', 'lecturer_id', 'day', 'start_time', 'end_time'],
                properties: [
                    new OA\Property(property: 'course_id', type: 'integer', example: 1),
                    new OA\Property(property: 'lecturer_id', type: 'integer', example: 1),
                    new OA\Property(property: 'course_pic_id', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'day',
                        type: 'string',
                        enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        example: 'monday'
                    ),
                    new OA\Property(property: 'start_time', type: 'string', example: '07:00:00'),
                    new OA\Property(property: 'end_time', type: 'string', example: '10:35:00'),
                    new OA\Property(property: 'room', type: 'string', example: 'RT-01'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Schedule created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'lecturer_id' => 'required|exists:lecturers,id',
            'course_pic_id' => 'nullable|exists:course_pics,id',
            'day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i:s,H:i',
            'end_time' => 'required|date_format:H:i:s,H:i',
            'room' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $schedule = Schedule::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Schedule created successfully',
            'data' => $schedule->load(['course.semester', 'lecturer', 'coursePic']),
        ], 201);
    }

    #[OA\Get(
        path: '/api/schedules/{id}',
        operationId: 'getScheduleById',
        tags: ['Schedules'],
        summary: 'Get schedule by ID',
        description: 'Returns single schedule data',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $schedule = Schedule::with(['course.semester', 'lecturer', 'coursePic'])->find($id);

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    #[OA\Put(
        path: '/api/schedules/{id}',
        operationId: 'updateSchedule',
        tags: ['Schedules'],
        summary: 'Update existing schedule',
        description: 'Updates a schedule record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'course_id', type: 'integer', example: 1),
                    new OA\Property(property: 'lecturer_id', type: 'integer', example: 1),
                    new OA\Property(property: 'course_pic_id', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'day',
                        type: 'string',
                        enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        example: 'monday'
                    ),
                    new OA\Property(property: 'start_time', type: 'string', example: '07:00:00'),
                    new OA\Property(property: 'end_time', type: 'string', example: '10:35:00'),
                    new OA\Property(property: 'room', type: 'string', example: 'RT-01 (Lab TI)'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Schedule updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }

        $validated = $request->validate([
            'course_id' => 'sometimes|required|exists:courses,id',
            'lecturer_id' => 'sometimes|required|exists:lecturers,id',
            'course_pic_id' => 'nullable|exists:course_pics,id',
            'day' => 'sometimes|required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|required|date_format:H:i:s,H:i',
            'end_time' => 'sometimes|required|date_format:H:i:s,H:i',
            'room' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully',
            'data' => $schedule->load(['course.semester', 'lecturer', 'coursePic']),
        ]);
    }

    #[OA\Delete(
        path: '/api/schedules/{id}',
        operationId: 'deleteSchedule',
        tags: ['Schedules'],
        summary: 'Delete schedule',
        description: 'Deletes a schedule record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Schedule deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully',
        ]);
    }
}
