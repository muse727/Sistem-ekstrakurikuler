<?php

namespace App\Services;

use App\Enums\EvaluationPeriod;
use App\Enums\EvaluationStatus;
use App\Enums\RegistrationStatus;
use App\Models\ExtracurricularEvaluation;
use App\Models\ExtracurricularRegistration;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class EvaluationService
{
    public const W_ATTENDANCE = 0.25;
    public const W_ACTIVITY = 0.25;
    public const W_SKILL = 0.30;
    public const W_DISCIPLINE = 0.20;

    public static function calculateFinal(?float $attendance, ?float $activity, ?float $skill, ?float $discipline): ?float
    {
        if ($attendance === null || $activity === null || $skill === null || $discipline === null) {
            return null;
        }

        $final = ($attendance * self::W_ATTENDANCE)
            + ($activity * self::W_ACTIVITY)
            + ($skill * self::W_SKILL)
            + ($discipline * self::W_DISCIPLINE);

        return round($final, 2);
    }

    public function attendanceSummary(ExtracurricularEvaluation $evaluation): array
    {
        $registrationId = (int) $evaluation->registration_id;
        $sessionsQuery = \App\Models\ExtracurricularSession::query()
            ->where('extracurricular_id', (int) $evaluation->extracurricular_id)
            ->where('academic_year_id', (int) $evaluation->academic_year_id);

        $totalSessions = (clone $sessionsQuery)->count();

        $counts = StudentAttendance::query()
            ->where('registration_id', $registrationId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $normalize = function ($v): string {
            return $v instanceof \BackedEnum ? $v->value : (string) $v;
        };
        $byStatus = [];
        foreach ($counts as $k => $v) {
            $byStatus[$normalize($k)] = (int) $v;
        }

        $hadir = $byStatus['hadir'] ?? 0;
        $izin = $byStatus['izin'] ?? 0;
        $sakit = $byStatus['sakit'] ?? 0;
        $alpa = $byStatus['alpa'] ?? 0;

        $percentage = $totalSessions > 0 ? round(($hadir / $totalSessions) * 100, 2) : 0.0;

        return [
            'total_sessions' => (int) $totalSessions,
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpa' => $alpa,
            'percentage' => $percentage,
        ];
    }

    public function assertCoachCanView(User $user, ExtracurricularEvaluation $evaluation): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        $coachId = (int) ($user->coach_id ?? 0);
        if ($coachId <= 0) {
            throw new ConflictHttpException('Coach profile not linked to this account.');
        }
        $exists = DB::table('extracurricular_coach')
            ->where('extracurricular_id', (int) $evaluation->extracurricular_id)
            ->where('coach_id', $coachId)
            ->exists();
        if (! $exists) {
            throw new ConflictHttpException('Coach is not assigned to this extracurricular.');
        }
    }

    public function resolveRegistration(int $registrationId): ExtracurricularRegistration
    {
        $registration = ExtracurricularRegistration::with(['student', 'extracurricular', 'academicYear'])->find($registrationId);
        if (! $registration) {
            throw new UnprocessableEntityHttpException('Registration not found.');
        }

        return $registration;
    }

    public function assertEligible(ExtracurricularRegistration $registration): void
    {
        $status = $registration->status instanceof RegistrationStatus
            ? $registration->status
            : RegistrationStatus::from((string) $registration->status);

        $allowed = [RegistrationStatus::ACTIVE, RegistrationStatus::APPROVED];
        if (! in_array($status, $allowed, true)) {
            throw new UnprocessableEntityHttpException('Registration is not eligible for evaluation. Status must be active or approved.');
        }
    }

    public function assertCoachAssigned(User $user, ExtracurricularRegistration $registration): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        $coachId = (int) ($user->coach_id ?? 0);
        if ($coachId <= 0) {
            throw new ConflictHttpException('Coach profile not linked to this account.');
        }
        $exists = DB::table('extracurricular_coach')
            ->where('extracurricular_id', (int) $registration->extracurricular_id)
            ->where('coach_id', $coachId)
            ->exists();
        if (! $exists) {
            throw new ConflictHttpException('Coach is not assigned to this extracurricular.');
        }
    }

    protected function normalizeScore(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException('Scores must be numeric between 0 and 100.');
        }
        $f = (float) $value;
        if ($f < 0 || $f > 100) {
            throw new UnprocessableEntityHttpException('Scores must be between 0 and 100.');
        }

        return round($f, 2);
    }

    public function createDraft(User $evaluator, int $registrationId, string $period, array $scores = [], ?string $notes = null): ExtracurricularEvaluation
    {
        $periodEnum = $this->parsePeriod($period);
        $registration = $this->resolveRegistration($registrationId);
        $this->assertEligible($registration);
        $this->assertCoachAssigned($evaluator, $registration);

        $existing = ExtracurricularEvaluation::where('registration_id', $registration->id)
            ->where('evaluation_period', $periodEnum->value)
            ->first();
        if ($existing) {
            throw new ConflictHttpException('Evaluation for this registration and period already exists.');
        }

        $attendance = $this->normalizeScore($scores['attendance_score'] ?? null);
        $activity = $this->normalizeScore($scores['activity_score'] ?? null);
        $skill = $this->normalizeScore($scores['skill_score'] ?? null);
        $discipline = $this->normalizeScore($scores['discipline_score'] ?? null);
        $final = self::calculateFinal($attendance, $activity, $skill, $discipline);

        try {
            $evaluation = ExtracurricularEvaluation::create([
                'student_id' => (int) $registration->student_id,
                'registration_id' => (int) $registration->id,
                'extracurricular_id' => (int) $registration->extracurricular_id,
                'academic_year_id' => (int) $registration->academic_year_id,
                'evaluator_id' => (int) $evaluator->id,
                'evaluation_period' => $periodEnum->value,
                'attendance_score' => $attendance,
                'activity_score' => $activity,
                'skill_score' => $skill,
                'discipline_score' => $discipline,
                'final_score' => $final,
                'notes' => $notes,
                'status' => EvaluationStatus::DRAFT->value,
                'evaluated_at' => null,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ConflictHttpException('Evaluation for this registration and period already exists.');
            }
            throw $e;
        }

        return $evaluation->fresh()->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);
    }

    public function updateDraft(User $actor, ExtracurricularEvaluation $evaluation, array $scores = [], ?string $notes = null, bool $notesProvided = false): ExtracurricularEvaluation
    {
        $this->assertIsDraft($evaluation);

        if ($actor->isCoach() && ! $actor->isSuperAdmin()) {
            $registration = $evaluation->relationLoaded('registration') ? $evaluation->registration : $evaluation->registration()->first();
            if ($registration) {
                $this->assertCoachAssigned($actor, $registration);
            }
        }

        if (array_key_exists('attendance_score', $scores)) {
            $evaluation->attendance_score = $this->normalizeScore($scores['attendance_score']);
        }
        if (array_key_exists('activity_score', $scores)) {
            $evaluation->activity_score = $this->normalizeScore($scores['activity_score']);
        }
        if (array_key_exists('skill_score', $scores)) {
            $evaluation->skill_score = $this->normalizeScore($scores['skill_score']);
        }
        if (array_key_exists('discipline_score', $scores)) {
            $evaluation->discipline_score = $this->normalizeScore($scores['discipline_score']);
        }
        if ($notesProvided) {
            $evaluation->notes = $notes;
        }

        $evaluation->final_score = self::calculateFinal(
            $evaluation->attendance_score !== null ? (float) $evaluation->attendance_score : null,
            $evaluation->activity_score !== null ? (float) $evaluation->activity_score : null,
            $evaluation->skill_score !== null ? (float) $evaluation->skill_score : null,
            $evaluation->discipline_score !== null ? (float) $evaluation->discipline_score : null,
        );
        $evaluation->save();

        return $evaluation->fresh()->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);
    }

    public function publish(User $actor, ExtracurricularEvaluation $evaluation): ExtracurricularEvaluation
    {
        $this->assertIsDraft($evaluation);

        if ($actor->isCoach() && ! $actor->isSuperAdmin()) {
            $registration = $evaluation->relationLoaded('registration') ? $evaluation->registration : $evaluation->registration()->first();
            if ($registration) {
                $this->assertCoachAssigned($actor, $registration);
            }
        }

        $missing = [];
        foreach (['attendance_score', 'activity_score', 'skill_score', 'discipline_score'] as $field) {
            if ($evaluation->{$field} === null) {
                $missing[] = $field;
            }
        }
        if (! empty($missing)) {
            throw new UnprocessableEntityHttpException('Cannot publish: missing scores: '.implode(', ', $missing).'.');
        }

        $evaluation->final_score = self::calculateFinal(
            (float) $evaluation->attendance_score,
            (float) $evaluation->activity_score,
            (float) $evaluation->skill_score,
            (float) $evaluation->discipline_score,
        );
        $evaluation->status = EvaluationStatus::PUBLISHED->value;
        $evaluation->evaluated_at = now();
        $evaluation->save();

        return $evaluation->fresh()->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);
    }

    public function unpublish(User $actor, ExtracurricularEvaluation $evaluation): ExtracurricularEvaluation
    {
        $status = $evaluation->status instanceof EvaluationStatus ? $evaluation->status : EvaluationStatus::from((string) $evaluation->status);
        if ($status !== EvaluationStatus::PUBLISHED) {
            throw new UnprocessableEntityHttpException('Only published evaluations can be unpublished.');
        }
        $evaluation->status = EvaluationStatus::DRAFT->value;
        $evaluation->evaluated_at = null;
        $evaluation->save();

        return $evaluation->fresh()->load(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);
    }

    public function adminList(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularEvaluation::query()
            ->with(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator']);

        foreach (['academic_year_id', 'extracurricular_id', 'student_id', 'evaluator_id', 'evaluation_period', 'status', 'registration_id'] as $key) {
            if (! empty($filters[$key])) {
                $column = $key === 'evaluation_period' ? 'evaluation_period' : $key;
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('student_number', 'like', "%{$search}%"))
                    ->orWhereHas('extracurricular', fn ($eq) => $eq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function coachList(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularEvaluation::query()
            ->with(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator'])
            ->orderByDesc('id');

        if (! $user->isSuperAdmin()) {
            $coachId = (int) ($user->coach_id ?? 0);
            if ($coachId <= 0) {
                throw new ConflictHttpException('Coach profile not linked to this account.');
            }
            $assignedIds = DB::table('extracurricular_coach')->where('coach_id', $coachId)->pluck('extracurricular_id');
            $query->whereIn('extracurricular_id', $assignedIds);
        }

        foreach (['academic_year_id', 'extracurricular_id', 'student_id', 'evaluation_period', 'status', 'registration_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key === 'evaluation_period' ? 'evaluation_period' : $key, $filters[$key]);
            }
        }

        return $query->paginate($perPage);
    }

    public function studentHistory(int $studentId, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularEvaluation::query()
            ->with(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator'])
            ->where('student_id', $studentId)
            ->where('status', EvaluationStatus::PUBLISHED->value)
            ->orderByDesc('id');

        foreach (['academic_year_id', 'extracurricular_id', 'evaluation_period'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key === 'evaluation_period' ? 'evaluation_period' : $key, $filters[$key]);
            }
        }

        return $query->paginate($perPage);
    }

    public function eligibleRegistrations(User $coach, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = ExtracurricularRegistration::query()
            ->with(['student', 'extracurricular', 'academicYear'])
            ->whereIn('status', [RegistrationStatus::ACTIVE->value, RegistrationStatus::APPROVED->value])
            ->orderByDesc('id');

        if (! $coach->isSuperAdmin()) {
            $coachId = (int) ($coach->coach_id ?? 0);
            if ($coachId <= 0) {
                throw new ConflictHttpException('Coach profile not linked to this account.');
            }
            $assignedIds = DB::table('extracurricular_coach')->where('coach_id', $coachId)->pluck('extracurricular_id');
            $query->whereIn('extracurricular_id', $assignedIds);
        }
        foreach (['academic_year_id', 'extracurricular_id', 'student_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        return $query->paginate($perPage);
    }

    protected function parsePeriod(string $period): EvaluationPeriod
    {
        try {
            return EvaluationPeriod::from($period);
        } catch (\ValueError $e) {
            throw new UnprocessableEntityHttpException('Invalid evaluation period. Allowed: '.implode(',', EvaluationPeriod::values()).'.');
        }
    }

    protected function assertIsDraft(ExtracurricularEvaluation $evaluation): void
    {
        $status = $evaluation->status instanceof EvaluationStatus ? $evaluation->status : EvaluationStatus::from((string) $evaluation->status);
        if ($status !== EvaluationStatus::DRAFT) {
            throw new UnprocessableEntityHttpException("Evaluation is not editable. Current status: '{$status->value}'. Only draft can be updated/published.");
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique');
    }
}
