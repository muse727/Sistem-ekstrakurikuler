<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResource;
use App\Models\ExtracurricularEvaluation;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $studentId = (int) $request->user()->student_id;
        if ($studentId <= 0) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }

        $paginator = $this->service->studentHistory($studentId, $request->all());

        return ApiResponse::paginate(
            $paginator,
            EvaluationResource::collection($paginator->items()),
            'Evaluations retrieved successfully'
        );
    }

    public function show(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $studentId = (int) $request->user()->student_id;
        if ($studentId <= 0) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }
        if ((int) $evaluation->student_id !== $studentId) {
            return ApiResponse::error('Forbidden: not your evaluation.', null, 403);
        }
        $status = $evaluation->status instanceof \BackedEnum ? $evaluation->status->value : (string) $evaluation->status;
        if ($status !== 'published') {
            return ApiResponse::error('Not found', null, 404);
        }

        $evaluation->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation retrieved successfully');
    }

    public function summary(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $studentId = (int) $request->user()->student_id;
        if ($studentId <= 0) {
            return ApiResponse::error('Student profile not linked to this account.', null, 403);
        }
        if ((int) $evaluation->student_id !== $studentId) {
            return ApiResponse::error('Forbidden: not your evaluation.', null, 403);
        }
        $status = $evaluation->status instanceof \BackedEnum ? $evaluation->status->value : (string) $evaluation->status;
        if ($status !== 'published') {
            return ApiResponse::error('Not found', null, 404);
        }

        $evaluation->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        return ApiResponse::success(
            ['evaluation' => new EvaluationResource($evaluation), 'attendance_summary' => $this->service->attendanceSummary($evaluation)],
            'Attendance summary retrieved successfully'
        );
    }
}
