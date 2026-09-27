<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enumVal = fn ($v) => $v instanceof \BackedEnum ? $v->value : $v;
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'registration_id' => $this->registration_id,
            'registration' => new RegistrationResource($this->whenLoaded('registration')),
            'extracurricular_id' => $this->extracurricular_id,
            'extracurricular' => new ExtracurricularResource($this->whenLoaded('extracurricular')),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'evaluator_id' => $this->evaluator_id,
            'evaluator' => new UserResource($this->whenLoaded('evaluator')),
            'evaluation_period' => $enumVal($this->evaluation_period),
            'attendance_score' => $this->attendance_score !== null ? (float) $this->attendance_score : null,
            'activity_score' => $this->activity_score !== null ? (float) $this->activity_score : null,
            'skill_score' => $this->skill_score !== null ? (float) $this->skill_score : null,
            'discipline_score' => $this->discipline_score !== null ? (float) $this->discipline_score : null,
            'final_score' => $this->final_score !== null ? (float) $this->final_score : null,
            'notes' => $this->notes,
            'status' => $enumVal($this->status),
            'evaluated_at' => $this->evaluated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
