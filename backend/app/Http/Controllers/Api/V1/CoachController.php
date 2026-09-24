<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CoachResource;
use App\Models\Coach;
use Illuminate\Http\JsonResponse;

class CoachController extends Controller
{
    public function index(): JsonResponse
    {
        $coaches = Coach::query()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            CoachResource::collection($coaches),
            'Coaches retrieved successfully'
        );
    }
}
