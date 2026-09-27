<?php

namespace App\Http\Requests\Coach;

use App\Enums\EvaluationPeriod;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer', 'exists:extracurricular_registrations,id'],
            'evaluation_period' => ['required', 'string', 'in:'.implode(',', EvaluationPeriod::values())],
            'attendance_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activity_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'skill_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discipline_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
