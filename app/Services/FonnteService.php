<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected ?string $token;
    protected string $url;

    public function __construct()
    {
        $this->token = config('services.fonnte.token');
        $this->url = config('services.fonnte.url', 'https://api.fonnte.com/send');
    }

    /**
     * Format phone number to standard international format (e.g. 081234 -> 6281234)
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '08')) {
            $cleaned = '628' . substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '628' . substr($cleaned, 1);
        }

        return $cleaned;
    }

    /**
     * Send WhatsApp message via Fonnte
     */
    public function sendMessage(string $target, string $message): array
    {
        if (empty($this->token)) {
            Log::warning('Fonnte token is not set in environment.');
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Fonnte token is not configured in .env',
                'data' => null,
            ];
        }

        $formattedTarget = $this->formatPhoneNumber($target);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->asForm()->post($this->url, [
                'target' => $formattedTarget,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && $result['status'] === true) {
                return [
                    'success' => true,
                    'status' => 'sent',
                    'message' => 'Message sent successfully',
                    'data' => $result,
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $result['reason'] ?? ($result['message'] ?? 'Failed to send message via Fonnte'),
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            Log::error('Fonnte send message error: ' . $e->getMessage(), [
                'target' => $formattedTarget,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
