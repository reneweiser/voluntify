<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case SignupConfirmation = 'signup_confirmation';
    case PreShiftReminder24h = 'pre_shift_reminder_24h';
    case PreShiftReminder4h = 'pre_shift_reminder_4h';
    case EmailVerification = 'email_verification';
}
