<?php

namespace App\Enums;

enum StaffRole: string
{
    case Organizer = 'organizer';
    case VolunteerAdmin = 'volunteer_admin';
    case EntranceStaff = 'entrance_staff';
}
