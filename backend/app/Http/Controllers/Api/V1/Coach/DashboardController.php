<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Reporting\CoachDashboardService;
use App\Services\Reporting\ReportScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected CoachDashboardService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());

        try {
            $data = $this->service->get($request->user(), $scope);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 404);
        }

        return ApiResponse::success($data, 'Coach dashboard retrieved successfully');
    }
}
