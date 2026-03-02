<?php

namespace App\Enums;

enum SignupOutcomeType: string
{
    case Completed = 'completed';
    case PendingVerification = 'pending_verification';
}
