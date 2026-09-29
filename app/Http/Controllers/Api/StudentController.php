<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StudentController extends Controller
{
    #[OA\Get(
        path: '/api/students',
        operationId: 'getStudentsList',
        tags: ['Students'],
        summary: 'Get list of students',
        description: 'Returns paginated list of students ordered by attendance number. Filterable by is_active and search.',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Student::query();

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhere('attendance_number', $search);
            });
        }

        $perPage = $request->integer('per_page', 10);
        $students = $query->orderBy('attendance_number', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $students->items(),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/students',
        operationId: 'storeStudent',
        tags: ['Students'],
        summary: 'Create new student',
        description: 'Creates a new student record',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['attendance_number', 'name', 'nickname'],
                properties: [
                    new OA\Property(property: 'attendance_number', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Ahmad Dahlan'),
                    new OA\Property(property: 'nickname', type: 'string', example: 'Ahmad'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Student created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_number' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'nickname' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $student = Student::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student created successfully',
            'data' => $student,
        ], 201);
    }

    #[OA\Get(
        path: '/api/students/{id}',
        operationId: 'getStudentById',
        tags: ['Students'],
        summary: 'Get student by ID',
        description: 'Returns single student record',
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
        $student = Student::find($id);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);
    }

    #[OA\Put(
        path: '/api/students/{id}',
        operationId: 'updateStudent',
        tags: ['Students'],
        summary: 'Update existing student',
        description: 'Updates a student record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'attendance_number', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Ahmad Dahlan'),
                    new OA\Property(property: 'nickname', type: 'string', example: 'Ahmad'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Student updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }

        $validated = $request->validate([
            'attendance_number' => 'sometimes|required|integer|min:1',
            'name' => 'sometimes|required|string|max:255',
            'nickname' => 'sometimes|required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $student->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student updated successfully',
            'data' => $student,
        ]);
    }

    #[OA\Delete(
        path: '/api/students/{id}',
        operationId: 'deleteStudent',
        tags: ['Students'],
        summary: 'Delete student',
        description: 'Deletes a student record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Student deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student deleted successfully',
        ]);
    }
}
