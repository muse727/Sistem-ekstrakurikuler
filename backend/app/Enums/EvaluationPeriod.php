<?php

namespace App\Enums;

enum EvaluationPeriod: string
{
    case MIDTERM = 'midterm';
    case FINAL = 'final';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
