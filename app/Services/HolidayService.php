<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use peace2643\IndonesianHolidays\IndonesianHolidays;

class HolidayService
{
    /**
     * Check if a specific date (or today) is an active holiday
     */
    public function isHoliday(?string $date = null): bool
    {
        $targetDate = $date ?: now()->toDateString();

        return Holiday::active()
            ->onDate($targetDate)
            ->exists();
    }

    /**
     * Get the active holiday details for a specific date (or today)
     */
    public function getHoliday(?string $date = null): ?Holiday
    {
        $targetDate = $date ?: now()->toDateString();

        return Holiday::active()
            ->onDate($targetDate)
            ->first();
    }

    /**
     * Sync national holidays for a given year.
     * Uses public API (dayoffapi.vercel.app) with fallback to peace2643/indonesian-holidays package.
     */
    public function syncNationalHolidays(int $year): array
    {
        $synced = [];

        // 1. Try fetching from public Indonesian holiday API (supports all years)
        try {
            $response = Http::timeout(10)->get("https://api-hari-libur.vercel.app/api?year={$year}");

            if ($response->successful()) {
                $body = $response->json();
                $data = $body['data'] ?? $body;
                if (is_array($data)) {
                    foreach ($data as $item) {
                        $date = $item['date'] ?? ($item['tanggal'] ?? null);
                        $name = $item['description'] ?? ($item['keterangan'] ?? null);
                        $isCuti = !empty($item['is_cuti']) || (is_string($name) && str_contains(strtolower($name), 'cuti bersama'));

                        if ($date && $name) {
                            $holiday = Holiday::updateOrCreate(
                                [
                                    'name' => $name,
                                    'start_date' => $date,
                                    'type' => 'national',
                                ],
                                [
                                    'end_date' => $date,
                                    'description' => $isCuti ? 'Cuti Bersama Nasional' : 'Hari Libur Nasional',
                                    'is_active' => true,
                                ]
                            );
                            $synced[] = $holiday;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch holidays from public API for year {$year}: " . $e->getMessage());
        }

        // 2. Fallback to package if API didn't return any records and package has data for the year
        if (empty($synced)) {
            try {
                $packageHolidays = new IndonesianHolidays();
                $all = $packageHolidays->getAllHolidays();

                foreach ($all as $date => $name) {
                    if (str_starts_with($date, (string) $year)) {
                        $holiday = Holiday::updateOrCreate(
                            [
                                'name' => $name,
                                'start_date' => $date,
                                'type' => 'national',
                            ],
                            [
                                'end_date' => $date,
                                'description' => 'Hari Libur Nasional (Package)',
                                'is_active' => true,
                            ]
                        );
                        $synced[] = $holiday;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to load holidays from package: " . $e->getMessage());
            }
        }

        return $synced;
    }
}
