<?php

use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CoursePicController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SemesterController;
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

// Reminders & WhatsApp Management
Route::prefix('reminders')->group(function () {
    Route::post('/test-whatsapp', [ReminderController::class, 'testWhatsApp']);
    Route::post('/trigger-morning', [ReminderController::class, 'triggerMorning']);
    Route::post('/trigger-preclass', [ReminderController::class, 'triggerPreclass']);
    Route::get('/logs', [ReminderController::class, 'logs']);
});
