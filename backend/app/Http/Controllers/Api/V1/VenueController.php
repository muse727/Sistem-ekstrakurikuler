<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

class VenueController extends Controller
{
    public function index(): JsonResponse
    {
        $venues = Venue::query()
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            VenueResource::collection($venues),
            'Venues retrieved successfully'
        );
    }
}
