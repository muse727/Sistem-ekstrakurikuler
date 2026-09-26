<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSessionRequest;
use App\Http\Requests\Coach\BulkAttendanceRequest;
use App\Http\Requests\Coach\SingleAttendanceRequest;
use App\Http\Resources\CoachCheckInResource;
use App\Http\Resources\SessionResource;
use App\Http\Resources\StudentAttendanceResource;
use App\Models\CoachCheckIn;
use App\Models\ExtracurricularSession;
use App\Models\StudentAttendance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SessionController extends Controller
{
    public function __construct(
        protected AttendanceService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->adminSessions($request->all());

        return ApiResponse::paginate(
            $paginator,
            SessionResource::collection($paginator->items()),
            'Sessions retrieved successfully'
        );
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->service->createSession((int) $request->user()->id, $request->validated());

        return ApiResponse::success(new SessionResource($session), 'Session created successfully', 201);
    }

    public function show(ExtracurricularSession $session): JsonResponse
    {
        $session->load(['extracurricular', 'academicYear', 'venue', 'checkIns.coach']);
        $session->loadCount('attendances');

        return ApiResponse::success(new SessionResource($session), 'Session retrieved successfully');
    }

    public function open(ExtracurricularSession $session): JsonResponse
    {
        $session = $this->service->open($session);

        return ApiResponse::success(new SessionResource($session), 'Session opened successfully');
    }

    public function complete(ExtracurricularSession $session): JsonResponse
    {
        $session = $this->service->complete($session);

        return ApiResponse::success(new SessionResource($session), 'Session completed successfully');
    }

    public function cancel(ExtracurricularSession $session): JsonResponse
    {
        $session = $this->service->cancel($session);

        return ApiResponse::success(new SessionResource($session), 'Session cancelled successfully');
    }

    public function checkIns(ExtracurricularSession $session): JsonResponse
    {
        $checkIns = CoachCheckIn::query()->with(['coach', 'session.extracurricular', 'session.academicYear', 'session.venue'])
            ->where('session_id', $session->id)->orderBy('id')->get();

        return ApiResponse::success(CoachCheckInResource::collection($checkIns), 'Check-ins retrieved successfully');
    }

    public function attendance(Request $request, ExtracurricularSession $session): JsonResponse
    {
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

    public function correct(SingleAttendanceRequest $request, ExtracurricularSession $session, StudentAttendance $attendance): JsonResponse
    {
        if ((int) $attendance->session_id !== (int) $session->id) {
            return ApiResponse::error('Not found', null, 404);
        }

        try {
            $attendance = $this->service->correct($attendance, $request->user(), $request->validated()['status'], $request->validated()['notes'] ?? null);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::success(new StudentAttendanceResource($attendance), 'Attendance corrected successfully');
    }

    public function checkInPhoto(CoachCheckIn $checkIn): BinaryFileResponse|JsonResponse
    {
        $user = request()->user();
        $role = $user?->role instanceof \BackedEnum ? $user->role->value : (string) ($user?->role ?? '');

        if (! in_array($role, ['admin', 'super_admin'], true) && (int) ($user?->coach_id ?? 0) !== (int) $checkIn->coach_id) {
            return ApiResponse::error('Forbidden', null, 403);
        }

        if (! $checkIn->photo_path || ! Storage::disk('local')->exists($checkIn->photo_path)) {
            return ApiResponse::error('File not found', null, 404);
        }

        return response()->download(Storage::disk('local')->path($checkIn->photo_path));
    }
}
