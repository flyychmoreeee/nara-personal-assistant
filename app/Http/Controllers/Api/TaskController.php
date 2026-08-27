<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TaskController extends Controller
{
    #[OA\Get(
        path: '/api/tasks',
        operationId: 'getTasksList',
        tags: ['Tasks'],
        summary: 'Get list of tasks',
        description: 'Returns list of tasks with course information. Filterable by course_id and status (completed/pending)',
        parameters: [
            new OA\Parameter(name: 'course_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_completed', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'completed', 'all']))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Task::with(['course.semester']);

        if ($request->has('course_id')) {
            $query->where('course_id', $request->query('course_id'));
        }

        if ($request->has('is_completed')) {
            $query->where('is_completed', filter_var($request->query('is_completed'), FILTER_VALIDATE_BOOLEAN));
        } elseif ($request->has('status')) {
            $status = $request->query('status');
            if ($status === 'pending') {
                $query->pending();
            } elseif ($status === 'completed') {
                $query->completed();
            }
        }

        $tasks = $query->orderBy('is_completed', 'asc')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    #[OA\Post(
        path: '/api/tasks',
        operationId: 'storeTask',
        tags: ['Tasks'],
        summary: 'Create new task',
        description: 'Creates a new task assignment for a course',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['course_id', 'title'],
                properties: [
                    new OA\Property(property: 'course_id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string', example: 'PR untuk tugas 1'),
                    new OA\Property(property: 'description', type: 'string', example: 'Kerjakan hal 45-50 di buku cetak'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date-time', example: '2026-09-05 23:59:00'),
                    new OA\Property(property: 'is_completed', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Task created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'is_completed' => 'nullable|boolean',
        ]);

        $task = Task::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => $task->load('course'),
        ], 201);
    }

    #[OA\Get(
        path: '/api/tasks/{id}',
        operationId: 'getTaskById',
        tags: ['Tasks'],
        summary: 'Get task by ID',
        description: 'Returns single task data with course',
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
        $task = Task::with(['course.semester'])->find($id);

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Task not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    #[OA\Put(
        path: '/api/tasks/{id}',
        operationId: 'updateTask',
        tags: ['Tasks'],
        summary: 'Update existing task',
        description: 'Updates a task record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'course_id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string', example: 'PR untuk tugas 1'),
                    new OA\Property(property: 'description', type: 'string', example: 'Kerjakan hal 45-50 di buku cetak'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date-time', example: '2026-09-05 23:59:00'),
                    new OA\Property(property: 'is_completed', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Task not found'], 404);
        }

        $validated = $request->validate([
            'course_id' => 'sometimes|required|exists:courses,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'is_completed' => 'nullable|boolean',
        ]);

        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => $task->load('course'),
        ]);
    }

    #[OA\Patch(
        path: '/api/tasks/{id}/toggle-complete',
        operationId: 'toggleTaskComplete',
        tags: ['Tasks'],
        summary: 'Toggle task completed status',
        description: 'Toggles between completed and incomplete status',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function toggleComplete(int $id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Task not found'], 404);
        }

        $task->is_completed = !$task->is_completed;
        $task->save();

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'data' => $task->load('course'),
        ]);
    }

    #[OA\Delete(
        path: '/api/tasks/{id}',
        operationId: 'deleteTask',
        tags: ['Tasks'],
        summary: 'Delete task',
        description: 'Deletes a task record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Task not found'], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }
}
