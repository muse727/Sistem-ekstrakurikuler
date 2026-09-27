<?php

namespace App\Services\Reporting;

use App\Models\Extracurricular;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CoachDashboardService
{
    public function __construct(
        protected MembershipReportService $membership,
        protected PaymentReportService $payments,
        protected AttendanceReportService $attendance,
        protected EvaluationReportService $evaluations
    ) {}

    /**
     * @return array{assigned_ids: int[], assigned: array}
     */
    public function assignedExtracurriculars(User $user, ReportScope $scope): array
    {
        $coachId = (int) ($user->coach_id ?? 0);
        if ($coachId <= 0 && !$user->isSuperAdmin()) {
            throw new ConflictHttpException('Coach profile not linked to this account.');
        }

        $query = Extracurricular::query()->with(['academicYear']);
        if (!$user->isSuperAdmin()) {
            $assignedIds = DB::table('extracurricular_coach')->where('coach_id', $coachId)->pluck('extracurricular_id')->map(fn ($v) => (int) $v)->all();
            $query->whereIn('id', $assignedIds);
        }
        if ($scope->academicYearId) {
            $query->where('academic_year_id', $scope->academicYearId);
        }
        // If explicit filter, verify assignment (non-super-admin).
        if ($scope->extracurricularId && !$user->isSuperAdmin()) {
            $exists = DB::table('extracurricular_coach')
                ->where('coach_id', $coachId)
                ->where('extracurricular_id', $scope->extracurricularId)
                ->exists();
            if (!$exists) {
                throw new NotFoundHttpException('Extracurricular not assigned to this coach.');
            }
            $query->where('id', $scope->extracurricularId);
        } elseif ($scope->extracurricularId) {
            $query->where('id', $scope->extracurricularId);
        }

        $assigned = $query->orderBy('name')->get();
        $ids = $assigned->pluck('id')->map(fn ($v) => (int) $v)->all();

        // For non-super-admin without filter, ids = all assigned (in year scope).
        // For super-admin without coach profile, ids = filtered list.
        if (!$user->isSuperAdmin() && empty($scope->extracurricularId)) {
            // Ensure ids reflect all assigned in scope (already).
        }

        return [
            'assigned_ids' => $ids,
            'assigned' => $assigned->map(fn ($e) => [
                'id' => (int) $e->id,
                'name' => $e->name,
                'code' => $e->code,
                'academic_year_id' => (int) $e->academic_year_id,
                'is_active' => (bool) $e->is_active,
            ])->all(),
        ];
    }

    public function get(User $user, ReportScope $scope): array
    {
        $assigned = $this->assignedExtracurriculars($user, $scope);
        $ids = $assigned['assigned_ids'];

        // Constrain inner scope to assigned ids when filter not set.
        // We compute aggregates with explicit id list to avoid leaking unassigned data.
        $effectiveScope = clone $scope;
        // If no explicit ekskul filter but we have assigned ids, we aggregate per assigned set.
        // Services accept single extracurricular_id; for multi-id we aggregate manually here for dashboard.
        if (empty($effectiveScope->extracurricularId) && !empty($ids)) {
            $membership = $this->aggregateMembership($ids, $scope);
            $attendance = $this->aggregateAttendance($ids, $scope);
            $evaluations = $this->aggregateEvaluations($ids, $scope);
            $sessions = $this->sessionOverview($ids, $scope);
        } elseif (!empty($effectiveScope->extracurricularId)) {
            $membership = $this->membership->summary($effectiveScope);
            $attendance = $this->attendance->summary($effectiveScope);
            $evaluations = $this->evaluations->summary($effectiveScope);
            $sessions = $this->sessionOverview([$effectiveScope->extracurricularId], $scope);
        } else {
            // No assigned ekskul at all.
            $membership = ['total' => 0, 'draft' => 0, 'submitted' => 0, 'approved' => 0, 'active' => 0, 'rejected' => 0, 'cancelled' => 0, 'active_members' => 0];
            $attendance = (new AttendanceReportService())->summary($effectiveScope);
            $evaluations = (new EvaluationReportService())->summary($effectiveScope);
            $sessions = ['counts' => ['total' => 0, 'scheduled' => 0, 'open' => 0, 'completed' => 0, 'cancelled' => 0], 'upcoming' => [], 'recent' => []];
        }

        return [
            'scope' => $scope->toArray(),
            'assigned' => $assigned['assigned'],
            'assigned_count' => count($assigned['assigned']),
            'membership' => $membership,
            'sessions' => $sessions,
            'attendance' => $attendance,
            'evaluations' => $evaluations,
        ];
    }

    private function aggregateMembership(array $ids, ReportScope $scope): array
    {
        $q = DB::table('extracurricular_registrations')->whereIn('extracurricular_id', $ids);
        if ($scope->academicYearId) {
            $q->where('academic_year_id', $scope->academicYearId);
        }
        $total = (clone $q)->count();
        $rows = (clone $q)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $norm = [];
        foreach ($rows as $k => $v) {
            $norm[(string) $k] = (int) $v;
        }
        $active = $norm['active'] ?? 0;

        return [
            'total' => (int) $total,
            'draft' => (int) ($norm['draft'] ?? 0),
            'submitted' => (int) ($norm['submitted'] ?? 0),
            'approved' => (int) ($norm['approved'] ?? 0),
            'active' => (int) $active,
            'rejected' => (int) ($norm['rejected'] ?? 0),
            'cancelled' => (int) ($norm['cancelled'] ?? 0),
            'active_members' => (int) $active,
        ];
    }

    private function aggregateAttendance(array $ids, ReportScope $scope): array
    {
        // Reuse AttendanceReportService by looping? Simpler: build scoped queries manually.
        $sessQ = DB::table('extracurricular_sessions')->whereIn('extracurricular_id', $ids);
        if ($scope->academicYearId) {
            $sessQ->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->dateFrom) {
            $sessQ->whereDate('session_date', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $sessQ->whereDate('session_date', '<=', $scope->dateTo);
        }
        $sessCounts = (clone $sessQ)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $snorm = [];
        foreach ($sessCounts as $k => $v) {
            $snorm[(string) $k] = (int) $v;
        }

        $attQ = DB::table('student_attendances')
            ->join('extracurricular_sessions', 'extracurricular_sessions.id', '=', 'student_attendances.session_id')
            ->whereIn('extracurricular_sessions.extracurricular_id', $ids)
            ->where('extracurricular_sessions.status', '!=', 'cancelled');
        if ($scope->academicYearId) {
            $attQ->where('extracurricular_sessions.academic_year_id', $scope->academicYearId);
        }
        if ($scope->dateFrom) {
            $attQ->whereDate('extracurricular_sessions.session_date', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $attQ->whereDate('extracurricular_sessions.session_date', '<=', $scope->dateTo);
        }
        $attCounts = (clone $attQ)->selectRaw('student_attendances.status as status, COUNT(*) as c')->groupBy('student_attendances.status')->pluck('c', 'status')->toArray();
        $anorm = [];
        foreach ($attCounts as $k => $v) {
            $anorm[(string) $k] = (int) $v;
        }
        $hadir = $anorm['hadir'] ?? 0;
        $izin = $anorm['izin'] ?? 0;
        $sakit = $anorm['sakit'] ?? 0;
        $alpa = $anorm['alpa'] ?? 0;
        $total = $hadir + $izin + $sakit + $alpa;

        return [
            'sessions' => [
                'total' => (int) ((clone $sessQ)->count()),
                'scheduled' => (int) ($snorm['scheduled'] ?? 0),
                'open' => (int) ($snorm['open'] ?? 0),
                'completed' => (int) ($snorm['completed'] ?? 0),
                'cancelled' => (int) ($snorm['cancelled'] ?? 0),
            ],
            'records' => ['total' => $total, 'hadir' => $hadir, 'izin' => $izin, 'sakit' => $sakit, 'alpa' => $alpa],
            'attendance_percentage' => $total > 0 ? round(($hadir / $total) * 100, 2) : 0.0,
            'denominator_note' => 'hadir / total_recorded_attendance * 100; cancelled sessions excluded',
        ];
    }

    private function aggregateEvaluations(array $ids, ReportScope $scope): array
    {
        $q = DB::table('extracurricular_evaluations')->whereIn('extracurricular_id', $ids);
        if ($scope->academicYearId) {
            $q->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->dateFrom) {
            $q->whereDate('evaluated_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $q->whereDate('evaluated_at', '<=', $scope->dateTo);
        }
        $total = (clone $q)->count();
        $counts = (clone $q)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        $norm = [];
        foreach ($counts as $k => $v) {
            $norm[(string) $k] = (int) $v;
        }
        $published = $norm['published'] ?? 0;
        $avgRow = (clone $q)->where('status', 'published')->whereNotNull('final_score')->selectRaw('AVG(final_score) as avg_final, COUNT(*) as n')->first();
        $avgFinal = $avgRow && $avgRow->n > 0 ? round((float) $avgRow->avg_final, 2) : null;

        $eligQ = DB::table('extracurricular_registrations')->whereIn('extracurricular_id', $ids)->where('status', 'active');
        if ($scope->academicYearId) {
            $eligQ->where('academic_year_id', $scope->academicYearId);
        }
        $eligible = (int) $eligQ->count();

        return [
            'total' => $total,
            'draft' => (int) ($norm['draft'] ?? 0),
            'published' => (int) $published,
            'avg_final' => $avgFinal,
            'eligible_targets' => $eligible,
            'completion_percentage' => $eligible > 0 ? round(($published / $eligible) * 100, 2) : 0.0,
            'completion_note' => 'published / ACTIVE registrations in scope * 100; no dedicated target model, measurable counts only',
        ];
    }

    private function sessionOverview(array $ids, ReportScope $scope): array
    {
        $base = DB::table('extracurricular_sessions as s')
            ->leftJoin('extracurriculars as e', 'e.id', '=', 's.extracurricular_id')
            ->whereIn('s.extracurricular_id', $ids);
        if ($scope->academicYearId) {
            $base->where('s.academic_year_id', $scope->academicYearId);
        }
        if ($scope->dateFrom) {
            $base->whereDate('s.session_date', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $base->whereDate('s.session_date', '<=', $scope->dateTo);
        }
        $counts = (clone $base)->selectRaw('s.status as status, COUNT(*) as c')->groupBy('s.status')->pluck('c', 'status')->toArray();
        $norm = [];
        foreach ($counts as $k => $v) {
            $norm[(string) $k] = (int) $v;
        }

        $upcoming = (clone $base)
            ->whereIn('s.status', ['scheduled', 'open'])
            ->orderBy('s.session_date')->orderBy('s.id')
            ->limit(5)
            ->get(['s.id', 's.extracurricular_id', 'e.name as extracurricular_name', 's.session_date', 's.status', 's.topic'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'extracurricular_id' => (int) $r->extracurricular_id, 'extracurricular_name' => $r->extracurricular_name, 'session_date' => $r->session_date, 'status' => $r->status, 'topic' => $r->topic])
            ->all();

        $recent = (clone $base)
            ->where('s.status', 'completed')
            ->orderByDesc('s.session_date')->orderByDesc('s.id')
            ->limit(5)
            ->get(['s.id', 's.extracurricular_id', 'e.name as extracurricular_name', 's.session_date', 's.status', 's.topic'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'extracurricular_id' => (int) $r->extracurricular_id, 'extracurricular_name' => $r->extracurricular_name, 'session_date' => $r->session_date, 'status' => $r->status, 'topic' => $r->topic])
            ->all();

        return [
            'counts' => [
                'total' => (int) ((clone $base)->count()),
                'scheduled' => (int) ($norm['scheduled'] ?? 0),
                'open' => (int) ($norm['open'] ?? 0),
                'completed' => (int) ($norm['completed'] ?? 0),
                'cancelled' => (int) ($norm['cancelled'] ?? 0),
            ],
            'upcoming' => $upcoming,
            'recent' => $recent,
        ];
    }
}
