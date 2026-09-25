<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtracurricularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $academicYearId = $this->input('academic_year_id');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('extracurriculars', 'code')->where(function ($query) use ($academicYearId) {
                    return $query->where('academic_year_id', $academicYearId);
                }),
            ],
            'description' => ['nullable', 'string'],
            'fee_amount' => ['sometimes', 'integer', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
