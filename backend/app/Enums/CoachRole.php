<?php

namespace App\Enums;

enum CoachRole: string
{
    case PRIMARY = 'primary';
    case ASSISTANT = 'assistant';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
