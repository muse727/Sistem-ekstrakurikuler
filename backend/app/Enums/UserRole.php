<?php

namespace App\Enums;

enum UserRole: string
{
    CASE SUPER_ADMIN = 'super_admin';
    CASE ADMIN = 'admin';
    CASE COACH = 'coach';
    CASE STUDENT = 'student';
}
