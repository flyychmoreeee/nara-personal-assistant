<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SemesterController extends Controller
{
    #[OA\Get(
        path: '/api/semesters',
        operationId: 'getSemestersList',
        tags: ['Semesters'],
        summary: 'Get list of semesters',
        description: 'Returns list of all semesters',
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(): JsonResponse
    {
        $semesters = Semester::withCount('courses')->latest()->get();
        return response()->json([
            'success' => true,
            'data' => $semesters,
        ]);
    }

    #[OA\Post(
        path: '/api/semesters',
        operationId: 'storeSemester',
        tags: ['Semesters'],
        summary: 'Create new semester',
        description: 'Creates a new semester record',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: '2026/2027 Ganjil'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Semester created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (!empty($validated['is_active'])) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        $semester = Semester::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Semester created successfully',
            'data' => $semester,
        ], 201);
    }

    #[OA\Get(
        path: '/api/semesters/{id}',
        operationId: 'getSemesterById',
        tags: ['Semesters'],
        summary: 'Get semester by ID',
        description: 'Returns single semester data',
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
        $semester = Semester::with('courses')->find($id);

        if (!$semester) {
            return response()->json(['success' => false, 'message' => 'Semester not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $semester,
        ]);
    }

    #[OA\Put(
        path: '/api/semesters/{id}',
        operationId: 'updateSemester',
        tags: ['Semesters'],
        summary: 'Update existing semester',
        description: 'Updates a semester record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: '2026/2027 Genap'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Semester updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $semester = Semester::find($id);

        if (!$semester) {
            return response()->json(['success' => false, 'message' => 'Semester not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['is_active']) && $validated['is_active'] === true) {
            Semester::where('id', '!=', $id)->where('is_active', true)->update(['is_active' => false]);
        }

        $semester->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Semester updated successfully',
            'data' => $semester,
        ]);
    }

    #[OA\Delete(
        path: '/api/semesters/{id}',
        operationId: 'deleteSemester',
        tags: ['Semesters'],
        summary: 'Delete semester',
        description: 'Deletes a semester record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Semester deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $semester = Semester::find($id);

        if (!$semester) {
            return response()->json(['success' => false, 'message' => 'Semester not found'], 404);
        }

        $semester->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semester deleted successfully',
        ]);
    }
}
