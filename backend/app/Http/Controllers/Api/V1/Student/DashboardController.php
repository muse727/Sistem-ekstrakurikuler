<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportScope;
use App\Services\Reporting\StudentDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected StudentDashboardService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Never accept ?student_id — derive from auth user only.
        $scope = ReportScope::from($request->all());

        try {
            $data = $this->service->get($request->user(), $scope);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::success($data, 'Student dashboard retrieved successfully');
    }
}
