<?php

namespace App\Services\Reporting;

use App\Models\ExtracurricularRegistration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MembershipReportService
{
    public function summary(ReportScope $scope): array
    {
        $base = ExtracurricularRegistration::query();
        $scope->applyRegistrationScope($base);
        if ($scope->status) {
            $base->where('extracurricular_registrations.status', $scope->status);
        }
        // Membership date semantics: lifecycle timestamps only when explicit.
        // If date_from/date_to provided, filter by created_at.
        $scope->applyDateOn($base, 'extracurricular_registrations.created_at');

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $norm = $this->normalize($rows);
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

    public function paginate(ReportScope $scope, array $filters = []): LengthAwarePaginator
    {
        $perPage = $scope->perPage($filters, 15);
        $query = ExtracurricularRegistration::query()
            ->with(['student', 'extracurricular', 'academicYear'])
            ->orderByDesc('id');
        $scope->applyRegistrationScope($query);
        if ($scope->status) {
            $query->where('extracurricular_registrations.status', $scope->status);
        }
        $scope->applyDateOn($query, 'extracurricular_registrations.created_at');
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->whereHas('student', fn ($sq) => $sq->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('extracurricular', fn ($eq) => $eq->where('name', 'like', "%{$s}%"));
            });
        }

        return $query->paginate($perPage);
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
