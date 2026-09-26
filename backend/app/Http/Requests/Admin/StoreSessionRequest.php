<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extracurricular_id' => ['required', 'integer', 'exists:extracurriculars,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'extracurricular_id.required' => 'Extracurricular is required.',
            'session_date.required' => 'Session date is required.',
            'start_time.required' => 'Start time is required.',
        ];
    }
}
