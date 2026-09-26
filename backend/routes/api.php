<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\Admin\AcademicYearController as AdminAcademicYearController;
use App\Http\Controllers\Api\V1\Admin\CoachController as AdminCoachController;
use App\Http\Controllers\Api\V1\Admin\ExtracurricularController as AdminExtracurricularController;
use App\Http\Controllers\Api\V1\Admin\RegistrationController as AdminRegistrationController;
use App\Http\Controllers\Api\V1\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\V1\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CoachController;
use App\Http\Controllers\Api\V1\ExtracurricularController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\Api\V1\Coach\SessionController as CoachSessionController;
use App\Http\Controllers\Api\V1\Student\PaymentController as StudentPaymentController;
use App\Http\Controllers\Api\V1\Student\RegistrationController as StudentRegistrationController;
use App\Http\Controllers\Api\V1\Student\SessionController as StudentSessionController;
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

    // Public / Protected Master Data Routes (Read-only endpoints from T002)
    Route::get('/academic-years', [AcademicYearController::class, 'index']);
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/venues', [VenueController::class, 'index']);
    Route::get('/extracurriculars', [ExtracurricularController::class, 'index']);

    // Admin Master Data Management Routes (T004)
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':admin,super_admin'])->prefix('admin')->group(function () {
        // Academic Years
        Route::get('/academic-years', [AdminAcademicYearController::class, 'index']);
        Route::post('/academic-years', [AdminAcademicYearController::class, 'store']);
        Route::get('/academic-years/{academicYear}', [AdminAcademicYearController::class, 'show']);
        Route::put('/academic-years/{academicYear}', [AdminAcademicYearController::class, 'update']);
        Route::patch('/academic-years/{academicYear}', [AdminAcademicYearController::class, 'update']);
        Route::post('/academic-years/{academicYear}/activate', [AdminAcademicYearController::class, 'activate']);
        Route::post('/academic-years/{academicYear}/deactivate', [AdminAcademicYearController::class, 'deactivate']);

        // Students
        Route::get('/students', [AdminStudentController::class, 'index']);
        Route::post('/students', [AdminStudentController::class, 'store']);
        Route::get('/students/{student}', [AdminStudentController::class, 'show']);
        Route::put('/students/{student}', [AdminStudentController::class, 'update']);
        Route::patch('/students/{student}', [AdminStudentController::class, 'update']);

        // Coaches
        Route::get('/coaches', [AdminCoachController::class, 'index']);
        Route::post('/coaches', [AdminCoachController::class, 'store']);
        Route::get('/coaches/{coach}', [AdminCoachController::class, 'show']);
        Route::put('/coaches/{coach}', [AdminCoachController::class, 'update']);
        Route::patch('/coaches/{coach}', [AdminCoachController::class, 'update']);

        // Venues
        Route::get('/venues', [AdminVenueController::class, 'index']);
        Route::post('/venues', [AdminVenueController::class, 'store']);
        Route::get('/venues/{venue}', [AdminVenueController::class, 'show']);
        Route::put('/venues/{venue}', [AdminVenueController::class, 'update']);
        Route::patch('/venues/{venue}', [AdminVenueController::class, 'update']);

        // Extracurriculars
        Route::get('/extracurriculars', [AdminExtracurricularController::class, 'index']);
        Route::post('/extracurriculars', [AdminExtracurricularController::class, 'store']);
        Route::get('/extracurriculars/{extracurricular}', [AdminExtracurricularController::class, 'show']);
        Route::put('/extracurriculars/{extracurricular}', [AdminExtracurricularController::class, 'update']);
        Route::patch('/extracurriculars/{extracurricular}', [AdminExtracurricularController::class, 'update']);
        Route::post('/extracurriculars/{extracurricular}/coaches', [AdminExtracurricularController::class, 'assignCoach']);
        Route::delete('/extracurriculars/{extracurricular}/coaches/{coach}', [AdminExtracurricularController::class, 'detachCoach']);
        Route::post('/extracurriculars/{extracurricular}/schedules', [AdminExtracurricularController::class, 'storeSchedule']);
        Route::delete('/extracurriculars/{extracurricular}/schedules/{schedule}', [AdminExtracurricularController::class, 'destroySchedule']);

        // Registrations (T005)
        Route::get('/registrations', [AdminRegistrationController::class, 'index']);
        Route::get('/registrations/{registration}', [AdminRegistrationController::class, 'show']);
        Route::post('/registrations/{registration}/approve', [AdminRegistrationController::class, 'approve']);
        Route::post('/registrations/{registration}/reject', [AdminRegistrationController::class, 'reject']);
        Route::post('/registrations/{registration}/activate', [AdminRegistrationController::class, 'activate']);
        Route::post('/registrations/{registration}/cancel', [AdminRegistrationController::class, 'cancel']);

        // Payments & invoices (T006)
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{invoice}', [AdminPaymentController::class, 'show']);
        Route::post('/payments/{invoice}/verify', [AdminPaymentController::class, 'verify']);
        Route::post('/payments/{invoice}/reject', [AdminPaymentController::class, 'reject']);
        Route::get('/payments/{invoice}/proofs/{proof}/file', [AdminPaymentController::class, 'downloadProof']);

        // Sessions & attendance (T007)
        Route::get('/sessions', [AdminSessionController::class, 'index']);
        Route::post('/sessions', [AdminSessionController::class, 'store']);
        Route::get('/sessions/{session}', [AdminSessionController::class, 'show']);
        Route::post('/sessions/{session}/open', [AdminSessionController::class, 'open']);
        Route::post('/sessions/{session}/complete', [AdminSessionController::class, 'complete']);
        Route::post('/sessions/{session}/cancel', [AdminSessionController::class, 'cancel']);
        Route::get('/sessions/{session}/check-ins', [AdminSessionController::class, 'checkIns']);
        Route::get('/sessions/{session}/attendance', [AdminSessionController::class, 'attendance']);
        Route::post('/sessions/{session}/attendance', [AdminSessionController::class, 'recordBulk']);
        Route::post('/sessions/{session}/attendance/{attendance}', [AdminSessionController::class, 'correct']);

        Route::get('/test', function () {
            return response()->json(['success' => true, 'message' => 'Admin authorized']);
        });
    });

    // Student Registration & Membership Lifecycle (T005)
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':student'])->prefix('student')->group(function () {
        Route::get('/extracurriculars', [StudentRegistrationController::class, 'extracurriculars']);
        Route::get('/extracurriculars/{extracurricular}', [StudentRegistrationController::class, 'extracurricularDetail']);
        Route::get('/registrations', [StudentRegistrationController::class, 'index']);
        Route::post('/registrations', [StudentRegistrationController::class, 'store']);
        Route::get('/registrations/{registration}', [StudentRegistrationController::class, 'show']);
        Route::post('/registrations/{registration}/cancel', [StudentRegistrationController::class, 'cancel']);

        // Payments & invoices (T006)
        Route::get('/payments', [StudentPaymentController::class, 'index']);
        Route::get('/payments/{invoice}', [StudentPaymentController::class, 'show']);
        Route::post('/payments/{invoice}/proof', [StudentPaymentController::class, 'uploadProof']);
        Route::get('/payments/{invoice}/proofs/{proof}/file', [StudentPaymentController::class, 'downloadProof']);

        // Sessions & attendance (T007, view own only)
        Route::get('/sessions', [StudentSessionController::class, 'index']);
        Route::get('/sessions/{session}', [StudentSessionController::class, 'show']);
        Route::get('/attendance', [StudentSessionController::class, 'attendance']);
        Route::get('/attendance/{attendance}', [StudentSessionController::class, 'showAttendance']);
    });

    // Coach sessions & attendance (T007, assigned only)
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class . ':coach'])->prefix('coach')->group(function () {
        Route::get('/sessions', [CoachSessionController::class, 'index']);
        Route::get('/sessions/{session}', [CoachSessionController::class, 'show']);
        Route::post('/sessions/{session}/check-in', [CoachSessionController::class, 'checkIn']);
        Route::get('/sessions/{session}/attendance', [CoachSessionController::class, 'attendance']);
        Route::post('/sessions/{session}/attendance', [CoachSessionController::class, 'recordBulk']);
        Route::post('/sessions/{session}/attendance/{student}', [CoachSessionController::class, 'recordOne']);
        Route::get('/attendance', [CoachSessionController::class, 'myAttendance']);
    });

    // Protected check-in photo (owner coach + admin/super_admin only)
    Route::middleware(['auth:sanctum'])->get('/check-ins/{checkIn}/photo', [AdminSessionController::class, 'checkInPhoto'])->name('api.v1.checkin.photo');

    // Example Role Restricted Test Endpoints
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':coach'])->get('/coach/test', function () {
        return response()->json(['success' => true, 'message' => 'Coach authorized']);
    });
    Route::middleware(['auth:sanctum', EnsureUserHasRole::class.':student'])->get('/student/test', function () {
        return response()->json(['success' => true, 'message' => 'Student authorized']);
    });
});
