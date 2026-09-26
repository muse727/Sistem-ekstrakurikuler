<?php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class CoachCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'integer', 'min:0', 'max:100000'],
            'device_captured_at' => ['nullable', 'date'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Latitude is required.',
            'longitude.required' => 'Longitude is required.',
            'accuracy_meters.required' => 'GPS accuracy is required.',
            'photo.required' => 'Selfie photo is required.',
            'photo.mimes' => 'Only JPG or PNG photos are allowed.',
            'photo.max' => 'Photo too large. Maximum 5MB.',
        ];
    }
}
