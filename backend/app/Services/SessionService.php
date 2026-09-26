<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRegistration;
use App\Models\ExtracurricularSession;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SessionService
{
    public function createSession(int $createdBy, array $data): ExtracurricularSession
    {
        $ekskul = Extracurricular::find($data['extracurricular_id'] ?? null);
        if (! $ekskul) {
            throw new UnprocessableEntityHttpException('Extracurricular not found.');
        }

        $academicYearId = $data['academic_year_id'] ?? $ekskul->academic_year_id;
        if (! $academicYearId) {
            throw new UnprocessableEntityHttpException('Academic year is required.');
        }

        $start = $this->normalizeTime($data['start_time'] ?? null);
        $end = $this->normalizeTime($data['end_time'] ?? null);
        if (! $start) {
            throw new UnprocessableEntityHttpException('Start time is required.');
        }
        if ($end !== null && strcmp($end, $start) <= 0) {
            throw new UnprocessableEntityHttpException('End time must be after start time.');
        }

        if (! empty($data['venue_id'])) {
            $venue = Venue::find($data['venue_id']);
            if (! $venue) {
                throw new UnprocessableEntityHttpException('Venue not found.');
            }
        }

        $session = ExtracurricularSession::create([
            'extracurricular_id' => $ekskul->id,
            'academic_year_id' => (int) $academicYearId,
            'venue_id' => $data['venue_id'] ?? null,
            'session_date' => $data['session_date'],
            'start_time' => $start,
            'end_time' => $end,
            'status' => SessionStatus::SCHEDULED->value,
            'topic' => $data['topic'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);

        return $session->fresh()->load(['extracurricular', 'academicYear', 'venue']);
    }

    public function open(ExtracurricularSession $session): ExtracurricularSession
    {
        $this->assertTransition($session, [SessionStatus::SCHEDULED], SessionStatus::OPEN);
        $session->status = SessionStatus::OPEN->value;
        $session->save();

        return $session->fresh()->load(['extracurricular', 'academicYear', 'venue', 'checkIns']);
    }

    public function complete(ExtracurricularSession $session): ExtracurricularSession
    {
        $this->assertTransition($session, [SessionStatus::OPEN], SessionStatus::COMPLETED);
        $session->status = SessionStatus::COMPLETED->value;
        $session->save();

        return $session->fresh()->load(['extracurricular', 'academicYear', 'venue', 'checkIns']);
    }

    public function cancel(ExtracurricularSession $session): ExtracurricularSession
    {
        $this->assertTransition($session, [SessionStatus::SCHEDULED, SessionStatus::OPEN], SessionStatus::CANCELLED);
        $session->status = SessionStatus::CANCELLED->value;
        $session->save();

        return $session->fresh()->load(['extracurricular', 'academicYear', 'venue', 'checkIns']);
    }

    public function adminSessions(array $filters = []): LengthAwarePaginator
    {
        $query = ExtracurricularSession::query()->with(['extracurricular', 'academicYear', 'venue', 'checkIns']);

        return $this->applySessionFilters($query, $filters);
    }

    public function coachSessions(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ExtracurricularSession::query()->with(['extracurricular', 'academicYear', 'venue', 'checkIns']);
        if (! $user->isSuperAdmin()) {
            $coachId = (int) ($user->coach_id ?? 0);
            if ($coachId <= 0) {
                throw new ConflictHttpException('Coach profile not linked to this account.');
            }
            $query->whereHas('extracurricular.coaches', fn ($q) => $q->where('coaches.id', $coachId));
        }

        return $this->applySessionFilters($query, $filters);
    }

    public function studentSessions(int $studentId, array $filters = []): LengthAwarePaginator
    {
        $pairs = ExtracurricularRegistration::query()
            ->where('student_id', $studentId)
            ->whereIn('status', ['approved', 'active'])
            ->get(['extracurricular_id', 'academic_year_id']);

        $query = ExtracurricularSession::query()->with(['extracurricular', 'academicYear', 'venue', 'checkIns']);
        if ($pairs->isEmpty()) {
            $query->whereRaw('1 = 0');
            return $this->applySessionFilters($query, $filters);
        }
        $query->where(function ($q) use ($pairs) {
            foreach ($pairs as $p) {
                $q->orWhere(function ($qq) use ($p) {
                    $qq->where('extracurricular_id', $p->extracurricular_id)
                        ->where('academic_year_id', $p->academic_year_id);
                });
            }
        });

        return $this->applySessionFilters($query, $filters);
    }

    public function sessionAttendances(ExtracurricularSession $session, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = StudentAttendance::query()->with(['student', 'registration', 'recorder', 'updater', 'session'])
            ->where('session_id', $session->id)->orderBy('id');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function studentAttendances(int $studentId, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = StudentAttendance::query()
            ->with(['student', 'registration', 'session.extracurricular', 'session.academicYear', 'session.venue'])
            ->where('student_id', $studentId)->orderByDesc('id');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['session_id'])) {
            $query->where('session_id', (int) $filters['session_id']);
        }

        return $query->paginate($perPage);
    }

    public function coachAttendances(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = StudentAttendance::query()
            ->with(['student', 'registration', 'session.extracurricular', 'session.academicYear'])
            ->orderByDesc('id');
        if (! $user->isSuperAdmin()) {
            $coachId = (int) ($user->coach_id ?? 0);
            if ($coachId <= 0) {
                throw new ConflictHttpException('Coach profile not linked to this account.');
            }
            $assignedEkskulIds = DB::table('extracurricular_coach')->where('coach_id', $coachId)->pluck('extracurricular_id');
            $query->whereHas('session', fn ($q) => $q->whereIn('extracurricular_id', $assignedEkskulIds));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['session_id'])) {
            $query->where('session_id', (int) $filters['session_id']);
        }

        return $query->paginate($perPage);
    }

    public function assertCoachAssigned(ExtracurricularSession $session, int $coachId): void
    {
        if ($coachId <= 0) {
            throw new ConflictHttpException('Coach profile not linked to this account.');
        }
        $exists = DB::table('extracurricular_coach')
            ->where('extracurricular_id', $session->extracurricular_id)
            ->where('coach_id', $coachId)
            ->exists();
        if (! $exists) {
            throw new ConflictHttpException('Coach is not assigned to this extracurricular.');
        }
    }

    public function assertCoachCanAccessSession(User $user, ExtracurricularSession $session): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        $coachId = (int) ($user->coach_id ?? 0);
        if ($coachId <= 0) {
            throw new ConflictHttpException('Coach profile not linked to this account.');
        }
        $this->assertCoachAssigned($session, $coachId);
    }

    protected function applySessionFilters($query, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('topic', 'like', "%{$s}%")->orWhere('notes', 'like', "%{$s}%");
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['extracurricular_id'])) {
            $query->where('extracurricular_id', (int) $filters['extracurricular_id']);
        }
        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }
        if (! empty($filters['venue_id'])) {
            $query->where('venue_id', (int) $filters['venue_id']);
        }
        if (! empty($filters['date']) || ! empty($filters['session_date'])) {
            $d = $filters['date'] ?? $filters['session_date'];
            $query->whereDate('session_date', $d);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('session_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('session_date', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('session_date')->orderByDesc('id')->paginate($perPage);
    }

    protected function assertTransition(ExtracurricularSession $session, array $from, SessionStatus $to): void
    {
        $current = $session->status instanceof SessionStatus ? $session->status : SessionStatus::from((string) $session->status);
        if (! in_array($current, $from, true)) {
            $allowed = implode(',', array_map(fn ($s) => $s->value, $from));
            throw new UnprocessableEntityHttpException("Invalid transition from '{$current->value}'. Allowed from: {$allowed}.");
        }
    }

    protected function normalizeTime(?string $t): ?string
    {
        if ($t === null || $t === '') {
            return null;
        }
        $t = trim($t);
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            return $t.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }
        return $t;
    }
}
