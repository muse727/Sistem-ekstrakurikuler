<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVenueRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use App\Services\Admin\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function __construct(
        protected VenueService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->all());

        return ApiResponse::paginate(
            $paginator,
            VenueResource::collection($paginator->items()),
            'Venues retrieved successfully'
        );
    }

    public function store(StoreVenueRequest $request): JsonResponse
    {
        $venue = $this->service->create($request->validated());

        return ApiResponse::success(
            new VenueResource($venue),
            'Venue created successfully',
            201
        );
    }

    public function show(Venue $venue): JsonResponse
    {
        return ApiResponse::success(
            new VenueResource($venue),
            'Venue retrieved successfully'
        );
    }

    public function update(UpdateVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue = $this->service->update($venue, $request->validated());

        return ApiResponse::success(
            new VenueResource($venue),
            'Venue updated successfully'
        );
    }
}
