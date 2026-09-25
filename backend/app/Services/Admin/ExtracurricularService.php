<?php

namespace App\Services\Admin;

use App\Models\Extracurricular;
use App\Models\ExtracurricularSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExtracurricularService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = Extracurricular::query()->with(['academicYear', 'coaches', 'schedules.venue']);

        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Extracurricular
    {
        $extracurricular = Extracurricular::create($data);
        return $extracurricular->load(['academicYear', 'coaches', 'schedules.venue']);
    }

    public function update(Extracurricular $extracurricular, array $data): Extracurricular
    {
        $extracurricular->update($data);
        return $extracurricular->fresh()->load(['academicYear', 'coaches', 'schedules.venue']);
    }

    public function assignCoach(Extracurricular $extracurricular, int $coachId, string $role = 'primary'): Extracurricular
    {
        // T004 rule: only one primary coach per extracurricular (server-side).
        // Behaviour: explicitly REJECT a second primary assignment (no silent replace).
        if ($role === 'primary') {
            $existingPrimary = $extracurricular->coaches()
                ->wherePivot('role', 'primary')
                ->where('coaches.id', '!=', $coachId)
                ->exists();
            if ($existingPrimary) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'role' => ['An extracurricular can only have one primary coach. Remove or demote the existing primary coach first.'],
                ]);
            }
        }

        $extracurricular->coaches()->syncWithoutDetaching([
            $coachId => ['role' => $role]
        ]);

        return $extracurricular->fresh()->load(['academicYear', 'coaches', 'schedules.venue']);
    }

    public function detachCoach(Extracurricular $extracurricular, int $coachId): Extracurricular
    {
        $extracurricular->coaches()->detach($coachId);
        return $extracurricular->fresh()->load(['academicYear', 'coaches', 'schedules.venue']);
    }

    public function addSchedule(Extracurricular $extracurricular, array $data): ExtracurricularSchedule
    {
        $schedule = $extracurricular->schedules()->create($data);
        return $schedule->load('venue');
    }

    public function removeSchedule(Extracurricular $extracurricular, int $scheduleId): void
    {
        $extracurricular->schedules()->where('id', $scheduleId)->delete();
    }
}
