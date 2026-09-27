<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

class ReportScope
{
    public ?int $academicYearId;
    public ?AcademicYear $academicYear;
    public ?int $extracurricularId;
    public ?string $dateFrom;
    public ?string $dateTo;
    public ?string $status;

    public function __construct(array $filters = [])
    {
        $this->academicYearId = !empty($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null;
        $this->extracurricularId = !empty($filters['extracurricular_id']) ? (int) $filters['extracurricular_id'] : null;
        $this->dateFrom = !empty($filters['date_from']) ? (string) $filters['date_from'] : null;
        $this->dateTo = !empty($filters['date_to']) ? (string) $filters['date_to'] : null;
        $this->status = !empty($filters['status']) ? (string) $filters['status'] : null;

        if ($this->academicYearId) {
            $this->academicYear = AcademicYear::find($this->academicYearId);
        } else {
            $this->academicYear = AcademicYear::where('is_active', true)->orderByDesc('id')->first()
                ?? AcademicYear::orderByDesc('id')->first();
            $this->academicYearId = $this->academicYear ? (int) $this->academicYear->id : null;
        }
    }

    public static function from(array $filters = []): self
    {
        return new self($filters);
    }

    public function toArray(): array
    {
        return [
            'academic_year_id' => $this->academicYearId,
            'academic_year' => $this->academicYear ? [
                'id' => (int) $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_active' => (bool) $this->academicYear->is_active,
            ] : null,
            'extracurricular_id' => $this->extracurricularId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->status,
        ];
    }

    public function perPage(array $filters = [], int $default = 15): int
    {
        return min(max((int) ($filters['per_page'] ?? $default), 1), 100);
    }

    /**
     * Apply academic year + extracurricular scope to a registrations query.
     */
    public function applyRegistrationScope($query)
    {
        if ($this->academicYearId) {
            $query->where('extracurricular_registrations.academic_year_id', $this->academicYearId);
        }
        if ($this->extracurricularId) {
            $query->where('extracurricular_registrations.extracurricular_id', $this->extracurricularId);
        }
        return $query;
    }

    public function applyDateOn($query, string $column): mixed
    {
        if ($this->dateFrom) {
            $query->whereDate($column, '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate($column, '<=', $this->dateTo);
        }
        return $query;
    }
}
