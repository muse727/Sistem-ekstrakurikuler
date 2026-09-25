<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCoachRequest;
use App\Http\Requests\Admin\StoreExtracurricularRequest;
use App\Http\Requests\Admin\StoreScheduleRequest;
use App\Http\Requests\Admin\UpdateExtracurricularRequest;
use App\Http\Resources\ExtracurricularResource;
use App\Http\Resources\ExtracurricularScheduleResource;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\ExtracurricularSchedule;
use App\Services\Admin\ExtracurricularService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtracurricularController extends Controller
{
    public function __construct(
        protected ExtracurricularService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->all());

        return ApiResponse::paginate(
            $paginator,
            ExtracurricularResource::collection($paginator->items()),
            'Extracurriculars retrieved successfully'
        );
    }

    public function store(StoreExtracurricularRequest $request): JsonResponse
    {
        $extracurricular = $this->service->create($request->validated());

        return ApiResponse::success(
            new ExtracurricularResource($extracurricular),
            'Extracurricular created successfully',
            201
        );
    }

    public function show(Extracurricular $extracurricular): JsonResponse
    {
        $extracurricular->load(['academicYear', 'coaches', 'schedules.venue']);

        return ApiResponse::success(
            new ExtracurricularResource($extracurricular),
            'Extracurricular retrieved successfully'
        );
    }

    public function update(UpdateExtracurricularRequest $request, Extracurricular $extracurricular): JsonResponse
    {
        $extracurricular = $this->service->update($extracurricular, $request->validated());

        return ApiResponse::success(
            new ExtracurricularResource($extracurricular),
            'Extracurricular updated successfully'
        );
    }

    public function assignCoach(AssignCoachRequest $request, Extracurricular $extracurricular): JsonResponse
    {
        $coachId = (int) $request->input('coach_id');
        $role = $request->input('role', 'primary');

        $extracurricular = $this->service->assignCoach($extracurricular, $coachId, $role);

        return ApiResponse::success(
            new ExtracurricularResource($extracurricular),
            'Coach assigned successfully'
        );
    }

    public function detachCoach(Extracurricular $extracurricular, Coach $coach): JsonResponse
    {
        $extracurricular = $this->service->detachCoach($extracurricular, $coach->id);

        return ApiResponse::success(
            new ExtracurricularResource($extracurricular),
            'Coach detached successfully'
        );
    }

    public function storeSchedule(StoreScheduleRequest $request, Extracurricular $extracurricular): JsonResponse
    {
        $schedule = $this->service->addSchedule($extracurricular, $request->validated());

        return ApiResponse::success(
            new ExtracurricularScheduleResource($schedule),
            'Schedule created successfully',
            201
        );
    }

    public function destroySchedule(Extracurricular $extracurricular, ExtracurricularSchedule $schedule): JsonResponse
    {
        $this->service->removeSchedule($extracurricular, $schedule->id);

        return ApiResponse::success(
            null,
            'Schedule deleted successfully'
        );
    }
}
