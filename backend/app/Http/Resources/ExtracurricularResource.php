<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtracurricularResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'fee_amount' => (int) $this->fee_amount,
            'quota' => $this->quota !== null ? (int) $this->quota : null,
            'is_active' => (bool) $this->is_active,
            'coaches' => CoachResource::collection($this->whenLoaded('coaches')),
            'schedules' => ExtracurricularScheduleResource::collection($this->whenLoaded('schedules')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
