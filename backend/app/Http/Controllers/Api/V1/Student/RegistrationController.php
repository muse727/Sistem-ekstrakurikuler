<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreRegistrationRequest;
use App\Http\Resources\ExtracurricularResource;
use App\Http\Resources\RegistrationResource;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRegistration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        protected RegistrationService $service
    ) {}

    public function extracurriculars(Request $request): JsonResponse
    {
        $paginator = $this->service->availableExtracurriculars($request->all());

        return ApiResponse::paginate(
            $paginator,
            ExtracurricularResource::collection($paginator->items()),
            'Extracurriculars retrieved successfully'
        );
    }

    public function extracurricularDetail(Extracurricular $extracurricular): JsonResponse
    {
        $extracurricular->load(['academicYear', 'coaches', 'schedules.venue']);

        return ApiResponse::success(
            new ExtracurricularResource($extracurricular),
            'Extracurricular retrieved successfully'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (!$studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $paginator = $this->service->studentList((int) $studentId, $request->all());

        return ApiResponse::paginate(
            $paginator,
            RegistrationResource::collection($paginator->items()),
            'Registrations retrieved successfully'
        );
    }

    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if (!$studentId) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $registration = $this->service->submit(
            (int) $studentId,
            (int) $request->validated()['extracurricular_id']
        );

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration submitted successfully',
            201
        );
    }

    public function show(Request $request, ExtracurricularRegistration $registration): JsonResponse
    {
        if ((int) $registration->student_id !== (int) $request->user()->student_id) {
            return ApiResponse::error('Forbidden: not your registration.', null, 403);
        }

        $registration->load(['student', 'extracurricular', 'academicYear']);

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration retrieved successfully'
        );
    }

    public function cancel(Request $request, ExtracurricularRegistration $registration): JsonResponse
    {
        if ((int) $registration->student_id !== (int) $request->user()->student_id) {
            return ApiResponse::error('Forbidden: not your registration.', null, 403);
        }

        $registration = $this->service->cancel($registration, (int) $request->user()->id);

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration cancelled successfully'
        );
    }
}
