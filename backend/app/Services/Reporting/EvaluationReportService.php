<?php

namespace App\Services\Reporting;

use App\Models\ExtracurricularEvaluation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EvaluationReportService
{
    /**
     * published = status PUBLISHED; avg final over published where applicable.
     * completion = published / eligible_targets * 100. No dedicated target model exists,
     * so eligible_targets = ACTIVE registrations in scope; measurable counts only.
     * Date semantics: evaluated_at.
     */
    public function summary(ReportScope $scope): array
    {
        $base = $this->scopedQuery($scope);
        if ($scope->status) {
            $base->where('extracurricular_evaluations.status', $scope->status);
        }

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();
        $norm = $this->normalize($counts);
        $total = (int) ((clone $base)->count());
        $draft = (int) ($norm['draft'] ?? 0);
        $published = (int) ($norm['published'] ?? 0);

        $avgRow = (clone $base)
            ->where('status', 'published')
            ->whereNotNull('final_score')
            ->selectRaw('AVG(final_score) as avg_final, COUNT(*) as n')
            ->first();
        $avgFinal = $avgRow && $avgRow->n > 0 ? round((float) $avgRow->avg_final, 2) : null;

        $eligible = $this->eligibleTargets($scope);
        $completion = $eligible > 0 ? round(($published / $eligible) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'draft' => $draft,
            'published' => $published,
            'avg_final' => $avgFinal,
            'eligible_targets' => $eligible,
            'completion_percentage' => $completion,
            'completion_note' => 'published / ACTIVE registrations in scope * 100; no dedicated target model, measurable counts only',
        ];
    }

    public function paginate(ReportScope $scope, array $filters = []): LengthAwarePaginator
    {
        $perPage = $scope->perPage($filters, 15);
        $query = ExtracurricularEvaluation::query()
            ->with(['student', 'registration', 'extracurricular', 'academicYear', 'evaluator'])
            ->orderByDesc('id');

        if ($scope->academicYearId) {
            $query->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $query->where('extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $query->whereDate('evaluated_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $query->whereDate('evaluated_at', '<=', $scope->dateTo);
        }
        if ($scope->status) {
            $query->where('status', $scope->status);
        }

        return $query->paginate($perPage);
    }

    private function scopedQuery(ReportScope $scope)
    {
        $q = DB::table('extracurricular_evaluations');
        if ($scope->academicYearId) {
            $q->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $q->where('extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $q->whereDate('evaluated_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $q->whereDate('evaluated_at', '<=', $scope->dateTo);
        }
        return $q;
    }

    private function eligibleTargets(ReportScope $scope): int
    {
        $q = DB::table('extracurricular_registrations')->where('status', 'active');
        if ($scope->academicYearId) {
            $q->where('academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $q->where('extracurricular_id', $scope->extracurricularId);
        }
        return (int) $q->count();
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
