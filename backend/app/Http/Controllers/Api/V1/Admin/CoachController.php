<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCoachRequest;
use App\Http\Requests\Admin\UpdateCoachRequest;
use App\Http\Resources\CoachResource;
use App\Models\Coach;
use App\Services\Admin\CoachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function __construct(
        protected CoachService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->all());

        return ApiResponse::paginate(
            $paginator,
            CoachResource::collection($paginator->items()),
            'Coaches retrieved successfully'
        );
    }

    public function store(StoreCoachRequest $request): JsonResponse
    {
        $coach = $this->service->create($request->validated());

        return ApiResponse::success(
            new CoachResource($coach),
            'Coach created successfully',
            201
        );
    }

    public function show(Coach $coach): JsonResponse
    {
        return ApiResponse::success(
            new CoachResource($coach),
            'Coach retrieved successfully'
        );
    }

    public function update(UpdateCoachRequest $request, Coach $coach): JsonResponse
    {
        $coach = $this->service->update($coach, $request->validated());

        return ApiResponse::success(
            new CoachResource($coach),
            'Coach updated successfully'
        );
    }
}
