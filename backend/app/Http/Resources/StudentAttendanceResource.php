<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'session' => new SessionResource($this->whenLoaded('session')),
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'registration_id' => $this->registration_id,
            'registration' => new RegistrationResource($this->whenLoaded('registration')),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'recorder' => new UserResource($this->whenLoaded('recorder')),
            'recorded_at' => $this->recorded_at?->toISOString(),
            'updated_by' => $this->updated_by,
            'updater' => new UserResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
