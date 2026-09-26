<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'extracurricular_id' => $this->extracurricular_id,
            'extracurricular' => new ExtracurricularResource($this->whenLoaded('extracurricular')),
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'venue_id' => $this->venue_id,
            'venue' => new VenueResource($this->whenLoaded('venue')),
            'session_date' => $this->session_date?->format('Y-m-d'),
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'topic' => $this->topic,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'has_check_in' => $this->whenLoaded('checkIns', fn () => $this->checkIns->count() > 0),
            'check_in_count' => $this->whenLoaded('checkIns', fn () => $this->checkIns->count()),
            'attendance_count' => $this->whenCounted('attendances'),
            'attendances_count' => $this->when(isset($this->attendances_count), (int) ($this->attendances_count ?? 0)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
