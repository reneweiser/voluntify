<?php

namespace App\Enums;

enum ReminderWindow: string
{
    case TwentyFourHour = '24h';
    case FourHour = '4h';

    public function hours(): int
    {
        return match ($this) {
            self::TwentyFourHour => 24,
            self::FourHour => 4,
        };
    }

    public function flagColumn(): string
    {
        return match ($this) {
            self::TwentyFourHour => 'notification_24h_sent',
            self::FourHour => 'notification_4h_sent',
        };
    }

    public function templateType(): EmailTemplateType
    {
        return match ($this) {
            self::TwentyFourHour => EmailTemplateType::PreShiftReminder24h,
            self::FourHour => EmailTemplateType::PreShiftReminder4h,
        };
    }
}
