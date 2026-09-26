<?php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'attendance.*.status' => ['required', 'string', 'in:hadir,izin,sakit,alpa'],
            'attendance.*.notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance.required' => 'Attendance list is required.',
            'attendance.*.student_id.required' => 'Student ID is required.',
            'attendance.*.status.in' => 'Invalid attendance status.',
        ];
    }
}
