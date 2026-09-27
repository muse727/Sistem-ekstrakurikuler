<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\StoreEvaluationRequest;
use App\Http\Requests\Coach\UpdateEvaluationRequest;
use App\Http\Resources\EvaluationResource;
use App\Http\Resources\RegistrationResource;
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

    protected function httpCode(ConflictHttpException $e): int
    {
        return str_contains(strtolower($e->getMessage()), 'already exists') ? 409 : 403;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->coachList($request->user(), $request->all());
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::paginate(
            $paginator,
            EvaluationResource::collection($paginator->items()),
            'Evaluations retrieved successfully'
        );
    }

    public function eligible(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->eligibleRegistrations($request->user(), $request->all());
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        }

        return ApiResponse::paginate(
            $paginator,
            RegistrationResource::collection($paginator->items()),
            'Eligible registrations retrieved successfully'
        );
    }

    public function show(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            try {
                $this->service->assertCoachCanView($user, $evaluation);
            } catch (ConflictHttpException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            }
        }

        $evaluation->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation retrieved successfully');
    }

    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $evaluation = $this->service->createDraft(
                $request->user(),
                (int) $validated['registration_id'],
                (string) $validated['evaluation_period'],
                [
                    'attendance_score' => $validated['attendance_score'] ?? null,
                    'activity_score' => $validated['activity_score'] ?? null,
                    'skill_score' => $validated['skill_score'] ?? null,
                    'discipline_score' => $validated['discipline_score'] ?? null,
                ],
                $validated['notes'] ?? null
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, $this->httpCode($e));
        } catch (UnprocessableEntityHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation draft created successfully', 201);
    }

    public function update(UpdateEvaluationRequest $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            try {
                $this->service->assertCoachCanView($user, $evaluation);
            } catch (ConflictHttpException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            }
        }

        $validated = $request->validated();
        $scoreKeys = ['attendance_score', 'activity_score', 'skill_score', 'discipline_score'];
        $scores = array_intersect_key($validated, array_flip($scoreKeys));

        try {
            $evaluation = $this->service->updateDraft(
                $user,
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

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation draft updated successfully');
    }

    public function publish(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            try {
                $this->service->assertCoachCanView($user, $evaluation);
            } catch (ConflictHttpException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            }
        }

        try {
            $evaluation = $this->service->publish($user, $evaluation);
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (UnprocessableEntityHttpException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        return ApiResponse::success(new EvaluationResource($evaluation), 'Evaluation published successfully');
    }

    public function summary(Request $request, ExtracurricularEvaluation $evaluation): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            try {
                $this->service->assertCoachCanView($user, $evaluation);
            } catch (ConflictHttpException $e) {
                return ApiResponse::error($e->getMessage(), null, 403);
            }
        }

        $evaluation->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        return ApiResponse::success(
            ['evaluation' => new EvaluationResource($evaluation), 'attendance_summary' => $this->service->attendanceSummary($evaluation)],
            'Attendance summary retrieved successfully'
        );
    }
}
