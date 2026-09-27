<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEvaluationRequest;
use App\Http\Resources\EvaluationResource;
use App\Models\ExtracurricularEvaluation;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->adminList($request->all());

        return ApiResponse::paginate(
            $paginator,
            EvaluationResource::collection($paginator->items()),
            'Evaluations retrieved successfully'
        );
    }

    public function show(ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $evaluation->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation retrieved successfully');
    }

    public function update(UpdateEvaluationRequest $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $validated = $request->validated();
        $scoreKeys = ['attendance_score', 'activity_score', 'skill_score', 'discipline_score'];
        $scores = array_intersect_key($validated, array_flip($scoreKeys));

        try {
            $evaluation = $this->service->updateDraft(
                $request->user(),
                $evaluation,
                $scores,
                $validated['notes'] ?? null,
                array_key_exists('notes', $validated)
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (UnprocessableEntityHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation corrected successfully');
    }

    public function publish(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        try {
            $evaluation = $this->service->publish($request->user(), $evaluation);
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (UnprocessableEntityHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation published successfully');
    }

    public function unpublish(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        try {
            $evaluation = $this->service->unpublish($request->user(), $evaluation);
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (UnprocessableEntityHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation unpublished successfully');
    }

    public function summary(ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $evaluation->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        return ApiResponse::success(
            ['evaluation' => new EvaluationResource($evaluation), 'attendance_summary' => $this->service->attendanceSummary($evaluation)],
            'Attendance summary retrieved successfully'
        );
    }
}
