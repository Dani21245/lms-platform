<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case INSTRUCTOR = 'instructor';
    case STUDENT = 'student';
}
