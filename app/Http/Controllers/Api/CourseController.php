<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CourseController extends Controller
{
    #[OA\Get(
        path: '/api/courses',
        operationId: 'getCoursesList',
        tags: ['Courses'],
        summary: 'Get list of courses',
        description: 'Returns list of all courses with semester info',
        parameters: [
            new OA\Parameter(name: 'semester_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Course::with('semester');

        if ($request->has('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        $courses = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ]);
    }

    #[OA\Post(
        path: '/api/courses',
        operationId: 'storeCourse',
        tags: ['Courses'],
        summary: 'Create new course',
        description: 'Creates a new course record',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['semester_id', 'code', 'name'],
                properties: [
                    new OA\Property(property: 'semester_id', type: 'integer', example: 1),
                    new OA\Property(property: 'code', type: 'string', example: 'SIB261005'),
                    new OA\Property(property: 'name', type: 'string', example: 'Critical Thinking and Problem Solving'),
                    new OA\Property(property: 'credits', type: 'integer', example: 2),
                    new OA\Property(property: 'hours', type: 'integer', example: 4),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Course created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'code' => 'required|string|max:50|unique:courses,code',
            'name' => 'required|string|max:255',
            'credits' => 'nullable|integer|min:1|max:10',
            'hours' => 'nullable|integer|min:1|max:20',
        ]);

        $course = Course::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully',
            'data' => $course->load('semester'),
        ], 201);
    }

    #[OA\Get(
        path: '/api/courses/{id}',
        operationId: 'getCourseById',
        tags: ['Courses'],
        summary: 'Get course by ID',
        description: 'Returns single course data with schedules',
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
        $course = Course::with(['semester', 'schedules.lecturer', 'schedules.coursePic'])->find($id);

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $course,
        ]);
    }

    #[OA\Put(
        path: '/api/courses/{id}',
        operationId: 'updateCourse',
        tags: ['Courses'],
        summary: 'Update existing course',
        description: 'Updates a course record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'semester_id', type: 'integer', example: 1),
                    new OA\Property(property: 'code', type: 'string', example: 'SIB261005'),
                    new OA\Property(property: 'name', type: 'string', example: 'Critical Thinking and Problem Solving'),
                    new OA\Property(property: 'credits', type: 'integer', example: 2),
                    new OA\Property(property: 'hours', type: 'integer', example: 4),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Course updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $course = Course::find($id);

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        $validated = $request->validate([
            'semester_id' => 'sometimes|required|exists:semesters,id',
            'code' => 'sometimes|required|string|max:50|unique:courses,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'credits' => 'nullable|integer|min:1|max:10',
            'hours' => 'nullable|integer|min:1|max:20',
        ]);

        $course->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully',
            'data' => $course->load('semester'),
        ]);
    }

    #[OA\Delete(
        path: '/api/courses/{id}',
        operationId: 'deleteCourse',
        tags: ['Courses'],
        summary: 'Delete course',
        description: 'Deletes a course record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Course deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $course = Course::find($id);

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course not found'], 404);
        }

        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully',
        ]);
    }
}
