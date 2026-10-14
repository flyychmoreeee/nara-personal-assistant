<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KasWeek;
use App\Services\KasSpreadsheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KasWeekController extends Controller
{
    #[OA\Get(
        path: '/api/kas/weeks',
        operationId: 'getKasWeeksList',
        tags: ['Kas - Weeks'],
        summary: 'Get list of kas weeks',
        description: 'Returns list of kas weeks with payment counts and total collected',
        parameters: [
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = KasWeek::query()->withCount([
            'payments as paid_count' => function ($q) {
                $q->where('status', 'paid');
            }
        ])->withSum([
            'payments as total_collected' => function ($q) {
                $q->where('status', 'paid');
            }
        ], 'amount_paid');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        // Jika memfilter bulan/tahun, tetap sertakan iuran khusus (yang month/year-nya null)
        if ($request->filled('year')) {
            $year = $request->query('year');
            $query->where(function ($q) use ($year) {
                $q->where('year', $year)->orWhereNull('year');
            });
        }

        if ($request->filled('month')) {
            $month = $request->query('month');
            $query->where(function ($q) use ($month) {
                $q->where('month', $month)->orWhereNull('month');
            });
        }

        $weeks = $query->orderByRaw("COALESCE(year, 0) desc")
            ->orderByRaw("COALESCE(month, 0) desc")
            ->orderByRaw("COALESCE(week, 0) desc")
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $weeks,
        ]);
    }

    #[OA\Post(
        path: '/api/kas/weeks',
        operationId: 'storeKasWeek',
        tags: ['Kas - Weeks'],
        summary: 'Create new kas week or incidental due',
        description: 'Creates a new kas week period or incidental due (e.g. lomba, kegiatan)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Minggu 1 - September 2026'),
                    new OA\Property(property: 'type', type: 'string', enum: ['routine', 'incidental'], example: 'routine'),
                    new OA\Property(property: 'description', type: 'string', example: 'Iuran pendaftaran lomba futsal'),
                    new OA\Property(property: 'week', type: 'integer', example: 1),
                    new OA\Property(property: 'month', type: 'integer', example: 9),
                    new OA\Property(property: 'year', type: 'integer', example: 2026),
                    new OA\Property(property: 'amount', type: 'integer', example: 5000),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-09-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-09-07'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-09-15'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Kas week created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type', 'routine');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:routine,incidental',
            'description' => 'nullable|string',
            'week' => $type === 'routine' ? 'required|integer|min:1|max:5' : 'nullable|integer|min:1|max:5',
            'month' => $type === 'routine' ? 'required|integer|min:1|max:12' : 'nullable|integer|min:1|max:12',
            'year' => $type === 'routine' ? 'required|integer|min:2020|max:2100' : 'nullable|integer|min:2020|max:2100',
            'amount' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'due_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_active'])) {
            KasWeek::where('type', $type)->where('is_active', true)->update(['is_active' => false]);
        }

        $week = KasWeek::create($validated);

        app(KasSpreadsheetService::class)->syncQuietly();

        return response()->json([
            'success' => true,
            'message' => ($week->type === 'incidental' ? 'Iuran khusus' : 'Kas week') . ' created successfully',
            'data' => $week,
        ], 201);
    }

    #[OA\Get(
        path: '/api/kas/weeks/{id}',
        operationId: 'getKasWeekById',
        tags: ['Kas - Weeks'],
        summary: 'Get kas week by ID',
        description: 'Returns single kas week record',
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
        $week = KasWeek::find($id);

        if (!$week) {
            return response()->json([
                'success' => false,
                'message' => 'Kas week not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $week,
        ]);
    }

    #[OA\Put(
        path: '/api/kas/weeks/{id}',
        operationId: 'updateKasWeek',
        tags: ['Kas - Weeks'],
        summary: 'Update existing kas week',
        description: 'Updates a kas week record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Minggu 1 - September 2026'),
                    new OA\Property(property: 'type', type: 'string', enum: ['routine', 'incidental'], example: 'routine'),
                    new OA\Property(property: 'description', type: 'string', example: 'Iuran lomba'),
                    new OA\Property(property: 'week', type: 'integer', example: 1),
                    new OA\Property(property: 'month', type: 'integer', example: 9),
                    new OA\Property(property: 'year', type: 'integer', example: 2026),
                    new OA\Property(property: 'amount', type: 'integer', example: 5000),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-09-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-09-07'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-09-15'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kas week updated successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $week = KasWeek::find($id);

        if (!$week) {
            return response()->json([
                'success' => false,
                'message' => 'Kas week not found',
            ], 404);
        }

        $type = $request->input('type', $week->type ?? 'routine');

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'nullable|in:routine,incidental',
            'description' => 'nullable|string',
            'week' => $type === 'routine' ? 'sometimes|required|integer|min:1|max:5' : 'nullable|integer|min:1|max:5',
            'month' => $type === 'routine' ? 'sometimes|required|integer|min:1|max:12' : 'nullable|integer|min:1|max:12',
            'year' => $type === 'routine' ? 'sometimes|required|integer|min:2020|max:2100' : 'nullable|integer|min:2020|max:2100',
            'amount' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'due_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_active'])) {
            KasWeek::where('type', $type)->where('is_active', true)->where('id', '!=', $id)->update(['is_active' => false]);
        }

        $week->update($validated);

        app(KasSpreadsheetService::class)->syncQuietly();

        return response()->json([
            'success' => true,
            'message' => 'Kas week updated successfully',
            'data' => $week,
        ]);
    }

    #[OA\Delete(
        path: '/api/kas/weeks/{id}',
        operationId: 'deleteKasWeek',
        tags: ['Kas - Weeks'],
        summary: 'Delete kas week',
        description: 'Deletes a kas week record and associated payments',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Kas week deleted successfully'),
            new OA\Response(response: 404, description: 'Resource Not Found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $week = KasWeek::find($id);

        if (!$week) {
            return response()->json([
                'success' => false,
                'message' => 'Kas week not found',
            ], 404);
        }

        $week->delete();

        app(KasSpreadsheetService::class)->syncQuietly();

        return response()->json([
            'success' => true,
            'message' => 'Kas week deleted successfully',
        ]);
    }
}
