<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResource;
use App\Http\Resources\ExtracurricularResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\StudentAttendanceResource;
use App\Services\Reporting\AdminDashboardService;
use App\Services\Reporting\AttendanceReportService;
use App\Services\Reporting\EvaluationReportService;
use App\Services\Reporting\MembershipReportService;
use App\Services\Reporting\PaymentReportService;
use App\Services\Reporting\ReportScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected MembershipReportService $membership,
        protected PaymentReportService $payments,
        protected AttendanceReportService $attendance,
        protected EvaluationReportService $evaluations,
        protected AdminDashboardService $dashboard
    ) {}

    public function membership(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());
        $paginator = $this->membership->paginate($scope, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Membership report retrieved successfully',
            'data' => [
                'scope' => $scope->toArray(),
                'summary' => $this->membership->summary($scope),
                'items' => RegistrationResource::collection($paginator->items()),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());
        $paginator = $this->payments->paginate($scope, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment report retrieved successfully',
            'data' => [
                'scope' => $scope->toArray(),
                'summary' => $this->payments->summary($scope),
                'items' => InvoiceResource::collection($paginator->items()),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());
        $paginator = $this->attendance->paginate($scope, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Attendance report retrieved successfully',
            'data' => [
                'scope' => $scope->toArray(),
                'summary' => $this->attendance->summary($scope),
                'items' => StudentAttendanceResource::collection($paginator->items()),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function evaluations(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());
        $paginator = $this->evaluations->paginate($scope, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Evaluation report retrieved successfully',
            'data' => [
                'scope' => $scope->toArray(),
                'summary' => $this->evaluations->summary($scope),
                'items' => EvaluationResource::collection($paginator->items()),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function extracurriculars(Request $request): JsonResponse
    {
        $scope = ReportScope::from($request->all());
        $paginator = $this->dashboard->extracurriculars($scope, $request->all());

        $items = collect($paginator->items())->map(function ($e) {
            $arr = (new ExtracurricularResource($e))->resolve(request());
            $arr['report'] = [
                'members_total' => (int) ($e->members_total ?? 0),
                'members_active' => (int) ($e->members_active ?? 0),
                'payments' => $e->report_payments ?? ['billed' => 0, 'paid' => 0, 'outstanding' => 0, 'invoices' => 0],
                'sessions' => $e->report_sessions ?? ['sessions_total' => 0, 'sessions_completed' => 0],
                'attendance' => $e->report_attendance ?? ['attendance_total' => 0, 'attendance_hadir' => 0, 'attendance_percentage' => 0.0],
                'evaluations' => $e->report_evaluations ?? ['evaluations_total' => 0, 'evaluations_published' => 0, 'evaluations_avg_final' => null],
            ];

            return $arr;
        })->all();

        return response()->json([
            'success' => true,
            'message' => 'Extracurricular report retrieved successfully',
            'data' => [
                'scope' => $scope->toArray(),
                'items' => $items,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
