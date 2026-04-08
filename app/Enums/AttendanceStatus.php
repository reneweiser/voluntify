<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case NoShow = 'no_show';
    case EnRoute = 'en_route';
    case Excused = 'excused';
}
