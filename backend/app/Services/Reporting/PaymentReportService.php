<?php

namespace App\Services\Reporting;

use App\Models\ExtracurricularInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentReportService
{
    /**
     * Summary per spec:
     * billed = SUM amount in scope; paid = SUM where PAID; outstanding = billed - paid.
     * Rejected proofs are not paid (status REJECTED never counts as paid).
     * Date semantics: invoice issued_at.
     */
    public function summary(ReportScope $scope): array
    {
        $base = $this->scopedQuery($scope);
        if ($scope->status) {
            $base->where('extracurricular_invoices.status', $scope->status);
        }

        $counts = (clone $base)
            ->selectRaw('extracurricular_invoices.status as status, COUNT(*) as c')
            ->groupBy('extracurricular_invoices.status')
            ->pluck('c', 'status')
            ->toArray();
        $norm = $this->normalize($counts);

        $sums = (clone $base)
            ->selectRaw("SUM(extracurricular_invoices.amount) as billed, SUM(CASE WHEN extracurricular_invoices.status = 'paid' THEN extracurricular_invoices.amount ELSE 0 END) as paid")
            ->first();

        $billed = (int) ($sums->billed ?? 0);
        $paid = (int) ($sums->paid ?? 0);
        $outstanding = max(0, $billed - $paid);

        return [
            'total' => array_sum(array_map('intval', $norm)) ?: (clone $base)->count(),
            'unpaid' => (int) ($norm['unpaid'] ?? 0),
            'pending_verification' => (int) ($norm['pending_verification'] ?? 0),
            'paid_count' => (int) ($norm['paid'] ?? 0),
            'rejected' => (int) ($norm['rejected'] ?? 0),
            'cancelled' => (int) ($norm['cancelled'] ?? 0),
            'billed' => $billed,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'currency' => 'IDR',
        ];
    }

    public function paginate(ReportScope $scope, array $filters = []): LengthAwarePaginator
    {
        $perPage = $scope->perPage($filters, 15);
        $query = ExtracurricularInvoice::query()
            ->with(['registration.student', 'registration.extracurricular', 'registration.academicYear'])
            ->orderByDesc('id');

        $this->applyScopeToEloquent($query, $scope);
        if ($scope->status) {
            $query->where('status', $scope->status);
        }

        return $query->paginate($perPage);
    }

    private function scopedQuery(ReportScope $scope)
    {
        $q = DB::table('extracurricular_invoices')
            ->join('extracurricular_registrations', 'extracurricular_registrations.id', '=', 'extracurricular_invoices.registration_id');

        if ($scope->academicYearId) {
            $q->where('extracurricular_registrations.academic_year_id', $scope->academicYearId);
        }
        if ($scope->extracurricularId) {
            $q->where('extracurricular_registrations.extracurricular_id', $scope->extracurricularId);
        }
        if ($scope->dateFrom) {
            $q->whereDate('extracurricular_invoices.issued_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $q->whereDate('extracurricular_invoices.issued_at', '<=', $scope->dateTo);
        }

        return $q;
    }

    private function applyScopeToEloquent($query, ReportScope $scope): void
    {
        if ($scope->academicYearId) {
            $query->whereHas('registration', fn ($q) => $q->where('academic_year_id', $scope->academicYearId));
        }
        if ($scope->extracurricularId) {
            $query->whereHas('registration', fn ($q) => $q->where('extracurricular_id', $scope->extracurricularId));
        }
        if ($scope->dateFrom) {
            $query->whereDate('issued_at', '>=', $scope->dateFrom);
        }
        if ($scope->dateTo) {
            $query->whereDate('issued_at', '<=', $scope->dateTo);
        }
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
