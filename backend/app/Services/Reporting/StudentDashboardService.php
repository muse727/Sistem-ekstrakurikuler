<?php

namespace App\Services\Reporting;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StudentDashboardService
{
    public function get(User $user, ReportScope $scope): array
    {
        $studentId = (int) ($user->student_id ?? 0);
        if ($studentId <= 0) {
            throw new ConflictHttpException('Student profile not linked to this account.');
        }

        // Membership: active ekskul + status breakdown, own only.
        $regQ = DB::table('extracurricular_registrations as r')
            ->leftJoin('extracurriculars as e', 'e.id', '=', 'r.extracurricular_id')
            ->where('r.student_id', $studentId);
        if ($scope->academicYearId) {
            $regQ->where('r.academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $regQ->where('r.extracurricular_id', $scope->extracurricularId);
        }
        $regs = (clone $regQ)->get(['r.id', 'r.extracurricular_id', 'r.academic_year_id', 'r.status', 'e.name as extracurricular_name', 'e.code as extracurricular_code']);
        $byStatus = [];
        foreach ($regs as $r) {
            $k = (string) $r->status;
            $byStatus[$k] = ($byStatus[$k] ?? 0) + 1;
        }
        $activeRegs = $regs->filter(fn ($r) => (string) $r->status === 'active')->values()->map(fn ($r) => [
            'registration_id' => (int) $r->id,
            'extracurricular_id' => (int) $r->extracurricular_id,
            'extracurricular_name' => $r->extracurricular_name,
            'extracurricular_code' => $r->extracurricular_code,
            'academic_year_id' => (int) $r->academic_year_id,
            'status' => (string) $r->status,
        ])->all();

        // Payments: own invoices in scope. billed/paid/outstanding; date = issued_at.
        $invQ = DB::table('extracurricular_invoices as i')
            ->join('extracurricular_registrations as r', 'r.id', '=', 'i.registration_id')
            ->where('r.student_id', $studentId);
        if ($scope->academicYearId) {
            $invQ->where('r.academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $invQ->where('r.extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $invQ->whereDate('i.issued_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $invQ->whereDate('i.issued_at', '<=', $scope->dateTo);
        }
        $invCounts = (clone $invQ)->selectRaw('i.status as status, COUNT(*) as c')->groupBy('i.status')->pluck('c', 'status')->toArray();
        $inorm = [];
        foreach ($invCounts as $k => $v) {
            $inorm[(string) $k] = (int) $v;
        }
        $sums = (clone $invQ)->selectRaw('SUM(i.amount) as billed, SUM(CASE WHEN i.status = \'paid\' THEN i.amount ELSE 0 END) as paid')->first();
        $billed = (int) ($sums->billed ?? 0);
        $paid = (int) ($sums->paid ?? 0);

        // Attendance: own records, cancelled sessions excluded; date = session_date.
        $attQ = DB::table('student_attendances as a')
            ->join('extracurricular_sessions as s', 's.id', '=', 'a.session_id')
            ->where('a.student_id', $studentId)
            ->where('s.status', '!=', 'cancelled');
        if ($scope->academicYearId) {
            $attQ->where('s.academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $attQ->where('s.extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $attQ->whereDate('s.session_date', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $attQ->whereDate('s.session_date', '<=', $scope->dateTo);
        }
        $attCounts = (clone $attQ)->selectRaw('a.status as status, COUNT(*) as c')->groupBy('a.status')->pluck('c', 'status')->toArray();
        $anorm = [];
        foreach ($attCounts as $k => $v) {
            $anorm[(string) $k] = (int) $v;
        }
        $hadir = $anorm['hadir'] ?? 0;
        $izin = $anorm['izin'] ?? 0;
        $sakit = $anorm['sakit'] ?? 0;
        $alpa = $anorm['alpa'] ?? 0;
        $attTotal = $hadir + $izin + $sakit + $alpa;

        // Evaluations: published only, own. No drafts.
        $evalQ = DB::table('extracurricular_evaluations as e')
            ->leftJoin('extracurriculars as x', 'x.id', '=', 'e.extracurricular_id')
            ->where('e.student_id', $studentId)
            ->where('e.status', 'published');
        if ($scope->academicYearId) {
            $evalQ->where('e.academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $evalQ->where('e.extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $evalQ->whereDate('e.evaluated_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $evalQ->whereDate('e.evaluated_at', '<=', $scope->dateTo);
        }
        $publishedCount = (int) (clone $evalQ)->count();
        $avgRow = (clone $evalQ)->whereNotNull('e.final_score')->selectRaw('AVG(e.final_score) as avg_final, COUNT(*) as n')->first();
        $avgFinal = $avgRow && ((int) $avgRow->n) > 0 ? round((float) $avgRow->avg_final, 2) : null;
        $latest = (clone $evalQ)
            ->orderByDesc('e.evaluated_at')->orderByDesc('e.id')
            ->limit(5)
            ->get(['e.id', 'e.extracurricular_id', 'x.name as extracurricular_name', 'e.evaluation_period', 'e.final_score', 'e.evaluated_at'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'extracurricular_id' => (int) $r->extracurricular_id,
                'extracurricular_name' => $r->extracurricular_name,
                'evaluation_period' => $r->evaluation_period,
                'final_score' => $r->final_score !== null ? (float) $r->final_score : null,
                'evaluated_at' => $r->evaluated_at,
            ])->all();

        return [
            'scope' => $scope->toArray(),
            'student_id' => $studentId,
            'membership' => [
                'total' => count($regs),
                'by_status' => $byStatus,
                'active' => $activeRegs,
                'active_count' => count($activeRegs),
            ],
            'payments' => [
                'total' => array_sum($inorm),
                'unpaid' => (int) ($inorm['unpaid'] ?? 0),
                'pending_verification' => (int) ($inorm['pending_verification'] ?? 0),
                'paid_count' => (int) ($inorm['paid'] ?? 0),
                'rejected' => (int) ($inorm['rejected'] ?? 0),
                'cancelled' => (int) ($inorm['cancelled'] ?? 0),
                'billed' => $billed,
                'paid' => $paid,
                'outstanding' => max(0, $billed - $paid),
                'currency' => 'IDR',
            ],
            'attendance' => [
                'total' => $attTotal,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'attendance_percentage' => $attTotal > 0 ? round(($hadir / $attTotal) * 100, 2) : 0.0,
                'denominator_note' => 'hadir / total_recorded_attendance * 100; cancelled sessions excluded',
            ],
            'evaluations' => [
                'published_count' => $publishedCount,
                'avg_final' => $avgFinal,
                'latest' => $latest,
            ],
        ];
    }
}
