<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Reporting\AdminDashboardService;
use App\Services\Reporting\ReportScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());
        $data = $this->service->get($scope);

        return ApiResponse::success($data, 'Admin dashboard retrieved successfully');
    }
}
