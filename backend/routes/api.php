<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\CoachController;
use App\Http\Controllers\Api\V1\ExtracurricularController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\VenueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/academic-years', [AcademicYearController::class, 'index']);
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/extracurriculars', [ExtracurricularController::class, 'index']);
});
