<?php

namespace App\Enums;

enum ScannerMode: string
{
    case Checkin = 'checkin';
    case GearPickup = 'gear_pickup';
}
