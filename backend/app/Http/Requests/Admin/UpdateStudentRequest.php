<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id ?? $this->route('student');

        return [
            'student_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($studentId)],
            'nisn' => ['nullable', 'string', 'max:50', Rule::unique('students', 'nisn')->ignore($studentId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
