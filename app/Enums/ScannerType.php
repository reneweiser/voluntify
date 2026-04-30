<?php

namespace App\Enums;

enum ScannerType: string
{
    case EntryStaff = 'entry_staff';
    case Gear = 'gear';
    case VolunteerAdmin = 'volunteer_admin';
}
