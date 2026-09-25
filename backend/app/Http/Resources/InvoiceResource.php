<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = str_starts_with($request->path(), 'api/v1/admin');

        $proofs = $this->whenLoaded('proofs');
        $latestProof = null;
        $latestVerification = null;
        $rejectionReason = null;
        if ($this->relationLoaded('proofs') && $this->proofs->isNotEmpty()) {
            $latest = $this->proofs->first();
            $latestProof = new PaymentProofResource($latest);
            $lv = $latest->relationLoaded('latestVerification') ? $latest->latestVerification : null;
            if ($lv) {
                $latestVerification = new PaymentVerificationResource($lv);
                $rejectionReason = $lv->rejection_reason;
            }
        }

        $data = [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'amount' => (int) $this->amount,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'issued_at' => $this->issued_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'registration_id' => $this->registration_id,
            'registration' => $this->whenLoaded('registration', function () {
                $reg = $this->registration;
                return [
                    'id' => $reg->id,
                    'status' => $reg->status instanceof \BackedEnum ? $reg->status->value : $reg->status,
                    'extracurricular' => $reg->relationLoaded('extracurricular') && $reg->extracurricular ? [
                        'id' => $reg->extracurricular->id,
                        'name' => $reg->extracurricular->name,
                        'code' => $reg->extracurricular->code,
                    ] : null,
                    'academic_year' => $reg->relationLoaded('academicYear') && $reg->academicYear ? [
                        'id' => $reg->academicYear->id,
                        'name' => $reg->academicYear->name,
                    ] : null,
                    'student' => $reg->relationLoaded('student') && $reg->student ? [
                        'id' => $reg->student->id,
                        'name' => $reg->student->name,
                        'student_number' => $reg->student->student_number,
                    ] : null,
                ];
            }),
            'latest_proof' => $latestProof,
            'latest_verification' => $latestVerification,
            'rejection_reason' => $rejectionReason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];

        if ($isAdmin) {
            $data['proofs'] = PaymentProofResource::collection($this->whenLoaded('proofs'));
        }

        return $data;
    }
}
