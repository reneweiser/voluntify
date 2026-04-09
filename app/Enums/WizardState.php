<?php

namespace App\Enums;

enum WizardState: string
{
    case EmailEntry = 'email_entry';
    case PendingVerification = 'pending_verification';
    case PersonalInfo = 'personal_info';
    case SelectingShifts = 'selecting_shifts';
    case GearAndFields = 'gear_and_fields';
    case Confirming = 'confirming';
    case Complete = 'complete';
    case Expired = 'expired';
}
