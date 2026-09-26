<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\BulkAttendanceRequest;
use App\Http\Requests\Coach\CoachCheckInRequest;
use App\Http\Requests\Coach\SingleAttendanceRequest;
use App\Http\Resources\CoachCheckInResource;
use App\Http\Resources\SessionResource;
use App\Http\Resources\StudentAttendanceResource;
use App\Models\ExtracurricularSession;
use App\Models\Student;
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
        $paginator = $this->service->coachSessions($request->user(), $request->all());

        return ApiResponse::paginate(
            $paginator,
            SessionResource::collection($paginator->items()),
            'Sessions retrieved successfully'
        );
    }

    public function show(Request $request, ExtracurricularSession $session): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            try {
                $this->service->assertCoachCanAccessSession($user, $session);
            } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            }
        }

        $session->load(['extracurricular', 'academicYear', 'venue', 'checkIns.coach']);
        $session->loadCount('attendances');

        return ApiResponse::success(new SessionResource($session), 'Session retrieved successfully');
    }

    public function checkIn(CoachCheckInRequest $request, ExtracurricularSession $session): JsonResponse
    {
        try {
            $checkIn = $this->service->checkIn(
                $session,
                $request->user(),
                $request->only(['latitude', 'longitude', 'accuracy_meters', 'device_captured_at']),
                $request->file('photo')
            );
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            $msg = $e->getMessage();
            $code = str_contains(strtolower($msg), 'already checked in') ? 409 : 403;

            return ApiResponse::error($msg, null, $code);
        }

        return ApiResponse::success(new CoachCheckInResource($checkIn), 'Check-in recorded successfully', 201);
    }

    public function attendance(Request $request, ExtracurricularSession $session): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            try {
                $this->service->assertCoachCanAccessSession($user, $session);
            } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            }
        }

        $paginator = $this->service->sessionAttendances($session, $request->all());

        return ApiResponse::paginate(
            $paginator,
            StudentAttendanceResource::collection($paginator->items()),
            'Attendance retrieved successfully'
        );
    }

    public function recordBulk(BulkAttendanceRequest $request, ExtracurricularSession $session): JsonResponse
    {
        try {
            $items = $this->service->bulkRecord($session, $request->user(), $request->validated()['attendance']);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::success(StudentAttendanceResource::collection($items), 'Attendance recorded successfully', 201);
    }

    public function recordOne(SingleAttendanceRequest $request, ExtracurricularSession $session, Student $student): JsonResponse
    {
        try {
            $attendance = $this->service->recordOne(
                $session,
                $request->user(),
                (int) $student->id,
                (string) $request->validated()['status'],
                $request->validated()['notes'] ?? null
            );
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::success(new StudentAttendanceResource($attendance), 'Attendance recorded successfully', 201);
    }

    public function myAttendance(Request $request): JsonResponse
    {
        $paginator = $this->service->coachAttendances($request->user(), $request->all());

        return ApiResponse::paginate(
            $paginator,
            StudentAttendanceResource::collection($paginator->items()),
            'Attendance retrieved successfully'
        );
    }
}
