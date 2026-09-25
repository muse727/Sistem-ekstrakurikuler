<?php

namespace App\Http\Requests\Admin;

use App\Enums\CoachRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $extracurricular = $this->route('extracurricular');
        $extracurricularId = $extracurricular?->id ?? $extracurricular;

        return [
            'coach_id' => [
                'required',
                'integer',
                'exists:coaches,id',
                Rule::unique('extracurricular_coach', 'coach_id')->where(function ($query) use ($extracurricularId) {
                    return $query->where('extracurricular_id', $extracurricularId);
                }),
            ],
            'role' => ['sometimes', 'required', Rule::enum(CoachRole::class)],
        ];
    }
}
