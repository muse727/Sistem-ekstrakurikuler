<?php

namespace App\Enums;

enum NotificationType: string
{
    case REGISTRATION_SUBMITTED = 'registration_submitted';
    case REGISTRATION_APPROVED = 'registration_approved';
    case REGISTRATION_REJECTED = 'registration_rejected';
    case PAYMENT_PROOF_SUBMITTED = 'payment_proof_submitted';
    case PAYMENT_PROOF_APPROVED = 'payment_proof_approved';
    case PAYMENT_PROOF_REJECTED = 'payment_proof_rejected';
    case EVALUATION_PUBLISHED = 'evaluation_published';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
