<?php

namespace App\Services\Admin;

use App\Models\AcademicYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AcademicYearService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = AcademicYear::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('start_date', 'desc')->paginate($perPage);
    }

    public function create(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $isActive = !empty($data['is_active']);

            if ($isActive) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            return AcademicYear::create($data);
        });
    }

    public function update(AcademicYear $academicYear, array $data): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $data) {
            if (isset($data['is_active']) && $data['is_active']) {
                AcademicYear::where('id', '!=', $academicYear->id)->update(['is_active' => false]);
            }

            $academicYear->update($data);
            return $academicYear->fresh();
        });
    }

    public function activate(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear) {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
            return $academicYear->fresh();
        });
    }

    public function deactivate(AcademicYear $academicYear): AcademicYear
    {
        $academicYear->update(['is_active' => false]);
        return $academicYear->fresh();
    }
}
