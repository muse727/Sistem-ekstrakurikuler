<?php

namespace App\Services\Admin;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);
        $query = Student::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_number', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('class_name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Student
    {
        return Student::create($data);
    }

    public function update(Student $student, array $data): Student
    {
        $student->update($data);
        return $student->fresh();
    }

    public function activate(Student $student): Student
    {
        $student->update(['is_active' => true]);
        return $student->fresh();
    }

    public function deactivate(Student $student): Student
    {
        $student->update(['is_active' => false]);
        return $student->fresh();
    }
}
