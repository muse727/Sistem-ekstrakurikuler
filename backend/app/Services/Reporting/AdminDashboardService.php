<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use App\Models\Coach;
use App\Models\Extracurricular;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function __construct(
        protected MembershipReportService $membership,
        protected PaymentReportService $payments,
        protected AttendanceReportService $attendance,
        protected EvaluationReportService $evaluations
    ) {}

    public function get(ReportScope $scope): array
    {
        $studentsTotal = (int) Student::query()->count();
        $studentsActive = (int) Student::query()->where('is_active', true)->count();

        $coachesTotal = (int) Coach::query()->count();
        $coachesActive = (int) Coach::query()->where('is_active', true)->count();

        $ekskulQuery = Extracurricular::query();
        if ($scope->academicYearId) {
            $ekskulQuery->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $ekskulQuery->where('id', $scope->extracurricularId);
        }
        $ekskulTotal = (int) (clone $ekskulQuery)->count();
        $ekskulActive = (int) (clone $ekskulQuery)->where('is_active', true)->count();

        $yearsTotal = (int) AcademicYear::query()->count();
        $yearsActive = (int) AcademicYear::query()->where('is_active', true)->count();

        return [
            'academic_year' => array_merge($scope->toArray()['academic_year'] ?? [], [
                'total_years' => $yearsTotal,
                'active_years' => $yearsActive,
            ]),
            'scope' => $scope->toArray(),
            'students' => [
                'total' => $studentsTotal,
                'active' => $studentsActive,
            ],
            'coaches' => [
                'total' => $coachesTotal,
                'active' => $coachesActive,
            ],
            'extracurriculars' => [
                'total' => $ekskulTotal,
                'active' => $ekskulActive,
            ],
            'membership' => $this->membership->summary($scope),
            'payments' => $this->payments->summary($scope),
            'attendance' => $this->attendance->summary($scope),
            'evaluations' => $this->evaluations->summary($scope),
        ];
    }

    public function extracurriculars(ReportScope $scope, array $filters = [])
    {
        $perPage = $scope->perPage($filters, 15);
        $query = Extracurricular::query()->with(['academicYear']);

        if ($scope->academicYearId) {
            $query->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $query->where('id', $scope->extracurricularId);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
            });
        }

        $query->withCount([
            'registrations as members_total' => function ($q) use ($scope) {
                if ($scope->academicYearId) {
                    $q->where('academic_year_id', $scope->academicYearId);
                }
            },
            'registrations as members_active' => function ($q) use ($scope) {
                $q->where('status', 'active');
                if ($scope->academicYearId) {
                    $q->where('academic_year_id', $scope->academicYearId);
                }
            },
        ]);

        $paginator = $query->orderBy('name')->paginate($perPage);

        // Enrich with payments billed/paid and sessions/attendance per ekskul via DB aggregation (avoid N+1).
        $ids = collect($paginator->items())->pluck('id')->all();
        $payMap = [];
        $sessMap = [];
        $attMap = [];
        $evalMap = [];
        if (!empty($ids)) {
            $payRows = DB::table('extracurricular_invoices')
                ->join('extracurricular_registrations', 'extracurricular_registrations.id', '=', 'extracurricular_invoices.registration_id')
                ->whereIn('extracurricular_registrations.extracurricular_id', $ids)
                ->when($scope->academicYearId, fn ($q) => $q->where('extracurricular_registrations.academic_year_id', $scope->academicYearId))
                ->selectRaw('extracurricular_registrations.extracurricular_id as eid, SUM(extracurricular_invoices.amount) as billed, SUM(CASE WHEN extracurricular_invoices.status = \'paid\' THEN extracurricular_invoices.amount ELSE 0 END) as paid, COUNT(*) as n')
                ->groupBy('extracurricular_registrations.extracurricular_id')
                ->get();
            foreach ($payRows as $r) {
                $payMap[(int) $r->eid] = ['billed' => (int) $r->billed, 'paid' => (int) $r->paid, 'outstanding' => max(0, (int) $r->billed - (int) $r->paid), 'invoices' => (int) $r->n];
            }

            $sessRows = DB::table('extracurricular_sessions')
                ->whereIn('extracurricular_id', $ids)
                ->when($scope->academicYearId, fn ($q) => $q->where('academic_year_id', $scope->academicYearId))
                ->selectRaw('extracurricular_id as eid, COUNT(*) as total, SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed')
                ->groupBy('extracurricular_id')
                ->get();
            foreach ($sessRows as $r) {
                $sessMap[(int) $r->eid] = ['sessions_total' => (int) $r->total, 'sessions_completed' => (int) $r->completed];
            }

            $attRows = DB::table('student_attendances')
                ->join('extracurricular_sessions', 'extracurricular_sessions.id', '=', 'student_attendances.session_id')
                ->whereIn('extracurricular_sessions.extracurricular_id', $ids)
                ->where('extracurricular_sessions.status', '!=', 'cancelled')
                ->when($scope->academicYearId, fn ($q) => $q->where('extracurricular_sessions.academic_year_id', $scope->academicYearId))
                ->selectRaw('extracurricular_sessions.extracurricular_id as eid, COUNT(*) as total, SUM(CASE WHEN student_attendances.status = \'hadir\' THEN 1 ELSE 0 END) as hadir')
                ->groupBy('extracurricular_sessions.extracurricular_id')
                ->get();
            foreach ($attRows as $r) {
                $total = (int) $r->total;
                $hadir = (int) $r->hadir;
                $attMap[(int) $r->eid] = ['attendance_total' => $total, 'attendance_hadir' => $hadir, 'attendance_percentage' => $total > 0 ? round(($hadir / $total) * 100, 2) : 0.0];
            }

            $evalRows = DB::table('extracurricular_evaluations')
                ->whereIn('extracurricular_id', $ids)
                ->when($scope->academicYearId, fn ($q) => $q->where('academic_year_id', $scope->academicYearId))
                ->selectRaw('extracurricular_id as eid, COUNT(*) as total, SUM(CASE WHEN status = \'published\' THEN 1 ELSE 0 END) as published, AVG(CASE WHEN status = \'published\' THEN final_score END) as avg_final')
                ->groupBy('extracurricular_id')
                ->get();
            foreach ($evalRows as $r) {
                $evalMap[(int) $r->eid] = [
                    'evaluations_total' => (int) $r->total,
                    'evaluations_published' => (int) $r->published,
                    'evaluations_avg_final' => $r->avg_final !== null ? round((float) $r->avg_final, 2) : null,
                ];
            }
        }

        $paginator->getCollection()->transform(function ($item) use ($payMap, $sessMap, $attMap, $evalMap) {
            $id = (int) $item->id;
            $item->report_payments = $payMap[$id] ?? ['billed' => 0, 'paid' => 0, 'outstanding' => 0, 'invoices' => 0];
            $item->report_sessions = $sessMap[$id] ?? ['sessions_total' => 0, 'sessions_completed' => 0];
            $item->report_attendance = $attMap[$id] ?? ['attendance_total' => 0, 'attendance_hadir' => 0, 'attendance_percentage' => 0.0];
            $item->report_evaluations = $evalMap[$id] ?? ['evaluations_total' => 0, 'evaluations_published' => 0, 'evaluations_avg_final' => null];
            return $item;
        });

        return $paginator;
    }
}
