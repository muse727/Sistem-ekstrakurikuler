<?php

namespace App\Enums;

enum PaymentVerificationStatus: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
