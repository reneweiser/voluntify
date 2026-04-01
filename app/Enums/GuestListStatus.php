<?php

namespace App\Enums;

enum GuestListStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
        };
    }
}
