<?php

namespace App\Services\Reporting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Models\StudentAttendance;
use App\Models\ExtracurricularSession;

class AttendanceReportService
{
    /**
     * attendance_percentage = hadir / total_recorded_attendance * 100
     * denominator = total recorded attendance rows in scope (excluding cancelled sessions).
     * Cancelled sessions excluded from both session counts and attendance aggregation.
     */
    public function summary(ReportScope $scope): array
    {
        $sessionBase = $this->scopedSessions($scope);
        $sessionCounts = (clone $sessionBase)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();
        $snorm = $this->normalize($sessionCounts);

        $attBase = $this->scopedAttendances($scope);
        if ($scope->status) {
            $attBase->where('student_attendances.status', $scope->status);
        }
        $attCounts = (clone $attBase)
            ->selectRaw('student_attendances.status as status, COUNT(*) as c')
            ->groupBy('student_attendances.status')
            ->pluck('c', 'status')
            ->toArray();
        $anorm = $this->normalize($attCounts);

        $hadir = (int) ($anorm['hadir'] ?? 0);
        $izin = (int) ($anorm['izin'] ?? 0);
        $sakit = (int) ($anorm['sakit'] ?? 0);
        $alpa = (int) ($anorm['alpa'] ?? 0);
        $total = $hadir + $izin + $sakit + $alpa;
        $percentage = $total > 0 ? round(($hadir / $total) * 100, 2) : 0.0;

        return [
            'sessions' => [
                'total' => (int) ((clone $sessionBase)->count()),
                'scheduled' => (int) ($snorm['scheduled'] ?? 0),
                'open' => (int) ($snorm['open'] ?? 0),
                'completed' => (int) ($snorm['completed'] ?? 0),
                'cancelled' => (int) ($snorm['cancelled'] ?? 0),
            ],
            'records' => [
                'total' => (int) $total,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
            ],
            'attendance_percentage' => $percentage,
            'denominator_note' => 'hadir / total_recorded_attendance * 100; cancelled sessions excluded',
        ];
    }

    public function paginate(ReportScope $scope, array $filters = []): LengthAwarePaginator
    {
        $perPage = $scope->perPage($filters, 15);
        $query = StudentAttendance::query()
            ->with(['student', 'registration', 'session.extracurricular', 'session.academicYear'])
            ->whereHas('session', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->orderByDesc('id');

        if ($scope->academicYearId) {
            $query->whereHas('session', fn ($q) => $q->where('academic_year_id', $scope->academicYearId));
        }
        if ($scope->extracurricularId) {
            $query->whereHas('session', fn ($q) => $q->where('extracurricular_id', $scope->extracurricularId));
        }
        if ($scope->dateFrom) {
            $query->whereHas('session', fn ($q) => $q->whereDate('session_date', '>=', $scope->dateFrom));
        }
        if ($scope->dateTo) {
            $query->whereHas('session', fn ($q) => $q->whereDate('session_date', '<=', $scope->dateTo));
        }
        if ($scope->status) {
            $query->where('status', $scope->status);
        }

        return $query->paginate($perPage);
    }

    private function scopedSessions(ReportScope $scope)
    {
        $q = DB::table('extracurricular_sessions');
        if ($scope->academicYearId) {
            $q->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $q->where('extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $q->whereDate('session_date', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $q->whereDate('session_date', '<=', $scope->dateTo);
        }
        return $q;
    }

    private function scopedAttendances(ReportScope $scope)
    {
        $q = DB::table('student_attendances')
            ->join('extracurricular_sessions', 'extracurricular_sessions.id', '=', 'student_attendances.session_id')
            ->where('extracurricular_sessions.status', '!=', 'cancelled');

        if ($scope->academicYearId) {
            $q->where('extracurricular_sessions.academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $q->where('extracurricular_sessions.extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $q->whereDate('extracurricular_sessions.session_date', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $q->whereDate('extracurricular_sessions.session_date', '<=', $scope->dateTo);
        }
        return $q;
    }

    private function normalize(array $rows): array
    {
        $out = [];
        foreach ($rows as $k => $v) {
            $key = $k instanceof \BackedEnum ? $k->value : (string) $k;
            $out[$key] = (int) $v;
        }
        return $out;
    }
}
