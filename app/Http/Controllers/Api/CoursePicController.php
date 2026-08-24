<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoursePic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CoursePicController extends Controller
{
    #[OA\Get(
        path: '/api/course-pics',
        operationId: 'getCoursePicsList',
        tags: ['Course PICs'],
        summary: 'Get list of course PICs',
        description: 'Returns list of all course PICs (Penanggung Jawab Mata Kuliah)',
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(): JsonResponse
    {
        $pics = CoursePic::withCount('schedules')->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => $pics,
        ]);
    }

    #[OA\Post(
        path: '/api/course-pics',
        operationId: 'storeCoursePic',
        tags: ['Course PICs'],
        summary: 'Create new course PIC',
        description: 'Creates a new course PIC record',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'gender', 'phone_number'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Rezky'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female'], example: 'male'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '081234567801'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Course PIC created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'phone_number' => 'required|string|max:30',
        ]);

        $pic = CoursePic::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course PIC created successfully',
            'data' => $pic,
        ], 201);
    }

    #[OA\Get(
        path: '/api/course-pics/{id}',
        operationId: 'getCoursePicById',
        tags: ['Course PICs'],
        summary: 'Get course PIC by ID',
        description: 'Returns single course PIC data',
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
        $pic = CoursePic::with(['schedules.course', 'schedules.lecturer'])->find($id);

        if (!$pic) {
            return response()->json(['success' => false, 'message' => 'Course PIC not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pic,
        ]);
    }

    #[OA\Put(
        path: '/api/course-pics/{id}',
        operationId: 'updateCoursePic',
        tags: ['Course PICs'],
        summary: 'Update existing course PIC',
        description: 'Updates a course PIC record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Rezky'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female'], example: 'male'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '081234567801'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Course PIC updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $pic = CoursePic::find($id);

        if (!$pic) {
            return response()->json(['success' => false, 'message' => 'Course PIC not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'gender' => 'sometimes|required|in:male,female',
            'phone_number' => 'sometimes|required|string|max:30',
        ]);

        $pic->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course PIC updated successfully',
            'data' => $pic,
        ]);
    }

    #[OA\Delete(
        path: '/api/course-pics/{id}',
        operationId: 'deleteCoursePic',
        tags: ['Course PICs'],
        summary: 'Delete course PIC',
        description: 'Deletes a course PIC record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Course PIC deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $pic = CoursePic::find($id);

        if (!$pic) {
            return response()->json(['success' => false, 'message' => 'Course PIC not found'], 404);
        }

        $pic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course PIC deleted successfully',
        ]);
    }
}
