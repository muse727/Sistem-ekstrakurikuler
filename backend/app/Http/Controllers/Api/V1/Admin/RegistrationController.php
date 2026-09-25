<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\ExtracurricularRegistration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        protected RegistrationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->adminList($request->all());

        return ApiResponse::paginate(
            $paginator,
            RegistrationResource::collection($paginator->items()),
            'Registrations retrieved successfully'
        );
    }

    public function show(ExtracurricularRegistration $registration): JsonResponse
    {
        $registration->load(['student', 'extracurricular', 'academicYear', 'approver', 'rejector', 'canceller']);

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration retrieved successfully'
        );
    }

    public function approve(Request $request, ExtracurricularRegistration $registration): JsonResponse
    {
        $registration = $this->service->approve($registration, (int) $request->user()->id);

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration approved successfully'
        );
    }

    public function reject(RejectRegistrationRequest $request, ExtracurricularRegistration $registration): JsonResponse
    {
        $registration = $this->service->reject(
            $registration,
            (int) $request->user()->id,
            (string) $request->validated()['rejection_reason']
        );

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration rejected successfully'
        );
    }

    public function activate(Request $request, ExtracurricularRegistration $registration): JsonResponse
    {
        $registration = $this->service->activate($registration, (int) $request->user()->id);

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration activated successfully'
        );
    }

    public function cancel(Request $request, ExtracurricularRegistration $registration): JsonResponse
    {
        $registration = $this->service->cancel($registration, (int) $request->user()->id);

        return ApiResponse::success(
            new RegistrationResource($registration),
            'Registration cancelled successfully'
        );
    }
}
