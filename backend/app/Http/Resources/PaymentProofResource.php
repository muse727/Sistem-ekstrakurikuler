<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => (int) $this->file_size,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'latest_verification' => new PaymentVerificationResource($this->whenLoaded('latestVerification')),
            'verifications' => PaymentVerificationResource::collection($this->whenLoaded('verifications')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
