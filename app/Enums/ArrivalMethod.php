<?php

namespace App\Enums;

enum ArrivalMethod: string
{
    case QrScan = 'qr_scan';
    case ManualLookup = 'manual_lookup';
}
