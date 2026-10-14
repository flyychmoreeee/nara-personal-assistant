<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KasSpreadsheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class KasSpreadsheetController extends Controller
{
    protected KasSpreadsheetService $spreadsheetService;

    public function __construct(KasSpreadsheetService $spreadsheetService)
    {
        $this->spreadsheetService = $spreadsheetService;
    }

    #[OA\Get(
        path: '/api/kas/spreadsheet/status',
        operationId: 'getKasSpreadsheetStatus',
        tags: ['Kas - Spreadsheet'],
        summary: 'Get Google Spreadsheet integration status',
        description: 'Returns connection status, last sync timestamp, and current configuration for Google Spreadsheet sync',
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function status(): JsonResponse
    {
        $status = $this->spreadsheetService->getStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    #[OA\Post(
        path: '/api/kas/spreadsheet/sync',
        operationId: 'syncKasSpreadsheet',
        tags: ['Kas - Spreadsheet'],
        summary: 'Trigger manual sync to Google Spreadsheet',
        description: 'Immediately sends all treasury summary, expenses, and student payment matrix data to Google Apps Script Webhook',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'webhook_url', type: 'string', example: 'https://script.google.com/macros/s/.../exec', description: 'Optional override for Google Apps Script Webhook URL')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Spreadsheet synced successfully'),
            new OA\Response(response: 422, description: 'Webhook URL not configured or invalid'),
            new OA\Response(response: 500, description: 'Sync failed')
        ]
    )]
    public function sync(Request $request): JsonResponse
    {
        $webhookUrl = $request->input('webhook_url');

        $result = $this->spreadsheetService->sync($webhookUrl);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Sinkronisasi ke Google Spreadsheet gagal',
                'error_detail' => $result['error_detail'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Spreadsheet berhasil disinkronkan',
            'synced_at' => $result['synced_at'] ?? now()->toIso8601String(),
            'data' => $result['data'] ?? null,
        ]);
    }

    #[OA\Get(
        path: '/api/kas/spreadsheet/payload',
        operationId: 'getKasSpreadsheetPayload',
        tags: ['Kas - Spreadsheet'],
        summary: 'Preview spreadsheet JSON payload',
        description: 'Returns the exact payload data that is sent to the Google Apps Script Webhook for testing & debugging',
        responses: [
            new OA\Response(response: 200, description: 'Successful operation')
        ]
    )]
    public function payload(): JsonResponse
    {
        $payload = $this->spreadsheetService->buildPayload();

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
