<?php

namespace App\Enums;

enum LessonType: string
{
    case VIDEO = 'video';
    case TEXT = 'text';
    case QUIZ = 'quiz';
}
