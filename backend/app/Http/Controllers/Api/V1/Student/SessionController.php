<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Http\Resources\StudentAttendanceResource;
use App\Models\ExtracurricularSession;
use App\Models\StudentAttendance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        protected AttendanceService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $paginator = $this->service->studentSessions((int) $studentId, $request->all());

        return ApiResponse::paginate(
            $paginator,
            SessionResource::collection($paginator->items()),
            'Sessions retrieved successfully'
        );
    }

    public function show(Request $request, ExtracurricularSession $session): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $eligible = \App\Models\ExtracurricularRegistration::query()
            ->where('student_id', (int) $studentId)
            ->where('extracurricular_id', (int) $session->extracurricular_id)
            ->where('academic_year_id', (int) $session->academic_year_id)
            ->whereIn('status', ['approved', 'active'])
            ->exists();

        if (! $eligible) {
            return ApiResponse::error('Forbidden: no eligible registration for this session.', null, 403);
        }

        $session->load(['extracurricular', 'academicYear', 'venue']);

        return ApiResponse::success(new SessionResource($session), 'Session retrieved successfully');
    }

    public function attendance(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (! $studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $paginator = $this->service->studentAttendances((int) $studentId, $request->all());

        return ApiResponse::paginate(
            $paginator,
            StudentAttendanceResource::collection($paginator->items()),
            'Attendance retrieved successfully'
        );
    }

    public function showAttendance(Request $request, StudentAttendance $attendance): JsonResponse
    {
        if ((int) $attendance->student_id !== (int) $request->user()->student_id) {
            return ApiResponse::error('Forbidden: not your attendance.', null, 403);
        }

        $attendance->load(['student', 'registration', 'session.extracurricular', 'session.academicYear', 'session.venue']);

        return ApiResponse::success(new StudentAttendanceResource($attendance), 'Attendance retrieved successfully');
    }
}
