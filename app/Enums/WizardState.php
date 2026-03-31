<?php

namespace App\Enums;

enum WizardState: string
{
    case SelectingShifts = 'selecting_shifts';
    case GearAndFields = 'gear_and_fields';
    case PersonalInfo = 'personal_info';
    case Confirming = 'confirming';
    case PendingVerification = 'pending_verification';
    case Complete = 'complete';
    case Expired = 'expired';
}
