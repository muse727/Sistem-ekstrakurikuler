<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExtracurricularResource;
use App\Models\Extracurricular;
use Illuminate\Http\JsonResponse;

class ExtracurricularController extends Controller
{
    public function index(): JsonResponse
    {
        $extracurriculars = Extracurricular::query()
            ->with(['academicYear', 'coaches', 'schedules.venue'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ExtracurricularResource::collection($extracurriculars),
            'Extracurriculars retrieved successfully'
        );
    }
}
