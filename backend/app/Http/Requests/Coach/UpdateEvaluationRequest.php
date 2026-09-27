<?php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activity_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'skill_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discipline_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
