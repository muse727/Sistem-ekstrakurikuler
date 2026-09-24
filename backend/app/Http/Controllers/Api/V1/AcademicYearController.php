<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;

class AcademicYearController extends Controller
{
    public function index(): JsonResponse
    {
        $academicYears = AcademicYear::query()
            ->orderBy('start_date', 'desc')
            ->get();

        return ApiResponse::success(
            AcademicYearResource::collection($academicYears),
            'Academic years retrieved successfully'
        );
    }
}
