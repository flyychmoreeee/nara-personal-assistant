<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class WhatsAppWebhookController extends Controller
{
    protected WhatsAppCommandHandler $commandHandler;

    public function __construct(WhatsAppCommandHandler $commandHandler)
    {
        $this->commandHandler = $commandHandler;
    }

    #[OA\Post(
        path: '/api/webhook/fonnte',
        operationId: 'handleFonnteWebhook',
        tags: ['WhatsApp'],
        summary: 'Handle incoming WhatsApp webhook from Fonnte',
        description: 'Processes incoming messages, evaluates bot commands (!tugas, !jadwal, !dosen, !help), and responds accordingly',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'sender', type: 'string', example: '120363028123456789@g.us'),
                    new OA\Property(property: 'message', type: 'string', example: '!tugas'),
                    new OA\Property(property: 'name', type: 'string', example: 'Ahmad'),
                    new OA\Property(property: 'member', type: 'string', example: '6281234567890'),
                    new OA\Property(property: 'device', type: 'string', example: '628999999999'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook processed')
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        // Handle Fonnte verification or health checks via GET
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'NARA Fonnte webhook endpoint is active and ready.',
            ]);
        }

        $payload = $request->all();

        // Fallback if payload is in raw JSON or urlencoded format
        if (empty($payload)) {
            $rawContent = $request->getContent();
            if (!empty($rawContent)) {
                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $payload = $decoded;
                } else {
                    parse_str($rawContent, $parsed);
                    if (is_array($parsed) && !empty($parsed)) {
                        $payload = $parsed;
                    }
                }
            }
        }

        Log::info('WhatsApp webhook received:', [
            'sender' => $payload['sender'] ?? null,
            'from' => $payload['from'] ?? null,
            'message' => $payload['message'] ?? null,
            'member' => $payload['member'] ?? null,
            'name' => $payload['name'] ?? null,
            'raw_payload' => $payload,
        ]);

        $result = $this->commandHandler->handle($payload);

        // Fonnte also supports auto-reply via 'reply' key in webhook response
        $responsePayload = [
            'success' => true,
            'data' => $result,
        ];

        if (!empty($result['reply_message'])) {
            $responsePayload['reply'] = $result['reply_message'];
        }

        return response()->json($responsePayload);
    }
}
