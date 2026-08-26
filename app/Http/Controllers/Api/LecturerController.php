<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LecturerController extends Controller
{
    #[OA\Get(
        path: '/api/lecturers',
        operationId: 'getLecturersList',
        tags: ['Lecturers'],
        summary: 'Get list of lecturers',
        description: 'Returns list of all lecturers',
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(): JsonResponse
    {
        $lecturers = Lecturer::withCount('schedules')->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => $lecturers,
        ]);
    }

    #[OA\Post(
        path: '/api/lecturers',
        operationId: 'storeLecturer',
        tags: ['Lecturers'],
        summary: 'Create new lecturer',
        description: 'Creates a new lecturer record',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'gender'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ir. Rudy Ariyanto, S.T., M.Cs.'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female'], example: 'male'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '081234567890'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Lecturer created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'phone_number' => 'nullable|string|max:30',
        ]);

        $lecturer = Lecturer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lecturer created successfully',
            'data' => $lecturer,
        ], 201);
    }

    #[OA\Get(
        path: '/api/lecturers/{id}',
        operationId: 'getLecturerById',
        tags: ['Lecturers'],
        summary: 'Get lecturer by ID',
        description: 'Returns single lecturer data',
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
        $lecturer = Lecturer::with(['schedules.course', 'schedules.coursePic'])->find($id);

        if (!$lecturer) {
            return response()->json(['success' => false, 'message' => 'Lecturer not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lecturer,
        ]);
    }

    #[OA\Put(
        path: '/api/lecturers/{id}',
        operationId: 'updateLecturer',
        tags: ['Lecturers'],
        summary: 'Update existing lecturer',
        description: 'Updates a lecturer record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Vivin Ayu Lestari, S.Pd., M.Kom.'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female'], example: 'female'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '081234567891'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Lecturer updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $lecturer = Lecturer::find($id);

        if (!$lecturer) {
            return response()->json(['success' => false, 'message' => 'Lecturer not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'gender' => 'sometimes|required|in:male,female',
            'phone_number' => 'nullable|string|max:30',
        ]);

        $lecturer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lecturer updated successfully',
            'data' => $lecturer,
        ]);
    }

    #[OA\Delete(
        path: '/api/lecturers/{id}',
        operationId: 'deleteLecturer',
        tags: ['Lecturers'],
        summary: 'Delete lecturer',
        description: 'Deletes a lecturer record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lecturer deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $lecturer = Lecturer::find($id);

        if (!$lecturer) {
            return response()->json(['success' => false, 'message' => 'Lecturer not found'], 404);
        }

        $lecturer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lecturer deleted successfully',
        ]);
    }
}
