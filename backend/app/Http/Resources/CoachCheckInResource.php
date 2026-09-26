<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachCheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $role = $user?->role instanceof \BackedEnum ? $user->role->value : (string) ($user?->role ?? '');
        $isOwner = $user && (int) ($user->coach_id ?? 0) === (int) $this->coach_id;
        $canSeePhoto = in_array($role, ['admin', 'super_admin'], true) || $isOwner;

        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'session' => new SessionResource($this->whenLoaded('session')),
            'coach_id' => $this->coach_id,
            'coach' => new CoachResource($this->whenLoaded('coach')),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'accuracy_meters' => $this->accuracy_meters !== null ? (int) $this->accuracy_meters : null,
            'distance_from_venue_meters' => $this->distance_from_venue_meters !== null ? (int) $this->distance_from_venue_meters : null,
            'device_captured_at' => $this->device_captured_at?->toISOString(),
            'server_received_at' => $this->server_received_at?->toISOString(),
            'status' => $this->status,
            'photo_url' => $canSeePhoto && $this->photo_path ? route('api.v1.checkin.photo', ['checkIn' => $this->id], false) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
