<?php

namespace App\Services\Admin;

use App\Models\Coach;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CoachService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = Coach::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Coach
    {
        return Coach::create($data);
    }

    public function update(Coach $coach, array $data): Coach
    {
        $coach->update($data);
        return $coach->fresh();
    }

    public function activate(Coach $coach): Coach
    {
        $coach->update(['is_active' => true]);
        return $coach->fresh();
    }

    public function deactivate(Coach $coach): Coach
    {
        $coach->update(['is_active' => false]);
        return $coach->fresh();
    }
}
