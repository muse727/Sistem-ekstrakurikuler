<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CoachController;
use App\Http\Controllers\Api\V1\ExtracurricularController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\VenueController;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Health Check
    Route::get('/health', [HealthController::class, 'index']);

    // Guest Auth Routes (with rate limiting for login)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

        // Authenticated Auth Routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/test', [AuthController::class, 'test']);
        });
    });

    // Public / Protected Master Data Routes
    Route::get('/academic-years', [AcademicYearController::class, 'index']);
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/extracurriculars', [ExtracurricularController::class, 'index']);

    // Example Role Restricted Test Endpoints
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':admin'])->get('/admin/test', function () {
        return response()->json(['success' => true, 'message' => 'Admin authorized']);
    });
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':coach'])->get('/coach/test', function () {
        return response()->json(['success' => true, 'message' => 'Coach authorized']);
    });
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':student'])->get('/student/test', function () {
        return response()->json(['success' => true, 'message' => 'Student authorized']);
    });
});
