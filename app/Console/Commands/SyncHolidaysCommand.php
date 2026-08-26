<?php

namespace App\Console\Commands;

use App\Services\HolidayService;
use Illuminate\Console\Command;

class SyncHolidaysCommand extends Command
{
    protected $signature = 'app:sync-holidays {year? : The year to sync (default: current year)}';
    protected $description = 'Sync Indonesian national holidays into the database';

    public function handle(HolidayService $holidayService): int
    {
        $year = (int) ($this->argument('year') ?: now()->year);

        $this->info("Syncing Indonesian national holidays for year {$year}...");

        $synced = $holidayService->syncNationalHolidays($year);

        if (empty($synced)) {
            $this->warn("No holidays found or synced for year {$year}.");
            return self::FAILURE;
        }

        $this->table(
            ['Date', 'Holiday Name', 'Type', 'Description'],
            array_map(function ($h) {
                return [
                    $h->start_date->format('Y-m-d'),
                    $h->name,
                    $h->type,
                    $h->description,
                ];
            }, $synced)
        );

        $this->info("Successfully synced " . count($synced) . " holidays for {$year}.");
        return self::SUCCESS;
    }
}
