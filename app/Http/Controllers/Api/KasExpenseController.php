<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KasExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KasExpenseController extends Controller
{
    #[OA\Get(
        path: '/api/kas/expenses',
        operationId: 'getKasExpensesList',
        tags: ['Kas - Expenses'],
        summary: 'Get list of kas expenses',
        description: 'Returns paginated list of kas expenses ordered by expense date',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = KasExpense::query();

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('title', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        }

        if ($request->filled('year')) {
            $query->whereYear('expense_date', $request->query('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('expense_date', $request->query('month'));
        }

        $perPage = $request->integer('per_page', 10);
        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/kas/expenses',
        operationId: 'storeKasExpense',
        tags: ['Kas - Expenses'],
        summary: 'Create new kas expense',
        description: 'Creates a new class treasury expense record',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'amount', 'expense_date'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Beli spidol & penghapus papan tulis'),
                    new OA\Property(property: 'amount', type: 'integer', example: 25000),
                    new OA\Property(property: 'expense_date', type: 'string', format: 'date', example: '2026-09-15'),
                    new OA\Property(property: 'receipt_image', type: 'string', nullable: true, example: 'https://example.com/nota.jpg'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Beli di fotokopi depan kampus'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Expense created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'expense_date' => 'required|date',
            'receipt_image' => 'nullable|string|max:1000',
            'notes' => 'nullable|string',
        ]);

        $expense = KasExpense::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully',
            'data' => $expense,
        ], 201);
    }

    #[OA\Get(
        path: '/api/kas/expenses/{id}',
        operationId: 'getKasExpenseById',
        tags: ['Kas - Expenses'],
        summary: 'Get kas expense by ID',
        description: 'Returns single kas expense detail',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Expense not found')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $expense = KasExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    #[OA\Put(
        path: '/api/kas/expenses/{id}',
        operationId: 'updateKasExpense',
        tags: ['Kas - Expenses'],
        summary: 'Update kas expense',
        description: 'Updates an existing expense record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Beli spidol & penghapus papan tulis'),
                    new OA\Property(property: 'amount', type: 'integer', example: 25000),
                    new OA\Property(property: 'expense_date', type: 'string', format: 'date', example: '2026-09-15'),
                    new OA\Property(property: 'receipt_image', type: 'string', nullable: true, example: 'https://example.com/nota.jpg'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Beli di fotokopi depan kampus'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Expense updated successfully'),
            new OA\Response(response: 404, description: 'Expense not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $expense = KasExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|integer|min:1',
            'expense_date' => 'sometimes|required|date',
            'receipt_image' => 'nullable|string|max:1000',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense,
        ]);
    }

    #[OA\Delete(
        path: '/api/kas/expenses/{id}',
        operationId: 'deleteKasExpense',
        tags: ['Kas - Expenses'],
        summary: 'Delete kas expense',
        description: 'Deletes a kas expense record',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Expense deleted successfully'),
            new OA\Response(response: 404, description: 'Expense not found')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $expense = KasExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully',
        ]);
    }
}
