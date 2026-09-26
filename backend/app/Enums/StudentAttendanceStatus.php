<?php

namespace App\Enums;

enum StudentAttendanceStatus: string
{
    case HADIR = 'hadir';
    case IZIN = 'izin';
    case SAKIT = 'sakit';
    case ALPA = 'alpa';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
