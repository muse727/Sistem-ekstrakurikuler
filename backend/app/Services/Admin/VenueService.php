<?php

namespace App\Services\Admin;

use App\Models\Venue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VenueService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = Venue::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Venue
    {
        return Venue::create($data);
    }

    public function update(Venue $venue, array $data): Venue
    {
        $venue->update($data);
        return $venue->fresh();
    }
}
