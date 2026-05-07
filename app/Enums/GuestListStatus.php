<?php

namespace App\Enums;

enum GuestListStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Sending inactive'),
            self::Confirmed => __('Sending active'),
        };
    }
}
