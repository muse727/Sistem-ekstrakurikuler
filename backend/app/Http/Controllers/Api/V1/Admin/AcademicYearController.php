<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicYearRequest;
use App\Http\Requests\Admin\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Services\Admin\AcademicYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function __construct(
        protected AcademicYearService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->all());

        return ApiResponse::paginate(
            $paginator,
            AcademicYearResource::collection($paginator->items()),
            'Academic years retrieved successfully'
        );
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $academicYear = $this->service->create($request->validated());

        return ApiResponse::success(
            new AcademicYearResource($academicYear),
            'Academic year created successfully',
            201
        );
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return ApiResponse::success(
            new AcademicYearResource($academicYear),
            'Academic year retrieved successfully'
        );
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $academicYear = $this->service->update($academicYear, $request->validated());

        return ApiResponse::success(
            new AcademicYearResource($academicYear),
            'Academic year updated successfully'
        );
    }

    public function activate(AcademicYear $academicYear): JsonResponse
    {
        $academicYear = $this->service->activate($academicYear);

        return ApiResponse::success(
            new AcademicYearResource($academicYear),
            'Academic year activated successfully'
        );
    }

    public function deactivate(AcademicYear $academicYear): JsonResponse
    {
        $academicYear = $this->service->deactivate($academicYear);

        return ApiResponse::success(
            new AcademicYearResource($academicYear),
            'Academic year deactivated successfully'
        );
    }
}
