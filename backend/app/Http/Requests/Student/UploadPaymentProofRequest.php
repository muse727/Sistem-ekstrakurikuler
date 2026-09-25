<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'proof.required' => 'Payment proof file is required.',
            'proof.mimes' => 'Only JPG, PNG, or PDF files are allowed.',
            'proof.max' => 'File too large. Maximum 5MB.',
        ];
    }
}
