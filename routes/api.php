<?php

use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CoursePicController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SemesterController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'app' => 'NARA Academic Assistant API',
        'status' => 'online',
        'server_time' => now()->toDateTimeString(),
    ]);
});

// Academic Entities CRUD
Route::apiResource('semesters', SemesterController::class);
Route::apiResource('lecturers', LecturerController::class);
Route::apiResource('course-pics', CoursePicController::class);
Route::apiResource('courses', CourseController::class);
Route::apiResource('schedules', ScheduleController::class);

// Task Management CRUD
Route::patch('tasks/{id}/toggle-complete', [TaskController::class, 'toggleComplete']);
Route::apiResource('tasks', TaskController::class);

// Holidays Management
Route::get('holidays/check', [HolidayController::class, 'check']);
Route::post('holidays/sync', [HolidayController::class, 'sync']);
Route::apiResource('holidays', HolidayController::class);

// Reminders & WhatsApp Management
Route::prefix('reminders')->group(function () {
    Route::post('/test-whatsapp', [ReminderController::class, 'testWhatsApp']);
    Route::post('/trigger-d-minus-one', [ReminderController::class, 'triggerDMinusOne']);
    Route::post('/trigger-morning', [ReminderController::class, 'triggerMorning']);
    Route::post('/trigger-preclass', [ReminderController::class, 'triggerPreclass']);
    Route::get('/logs', [ReminderController::class, 'logs']);
});

// WhatsApp Webhook (Fonnte - supports GET & POST)
Route::match(['get', 'post'], 'webhook/fonnte', [WhatsAppWebhookController::class, 'handle']);



