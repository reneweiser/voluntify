<?php

namespace App\Enums;

enum GearItemType: string
{
    case SizeSelection = 'size_selection';
    case Quantity = 'quantity';

    public function label(): string
    {
        return match ($this) {
            self::SizeSelection => 'Size Selection',
            self::Quantity => 'Quantity',
        };
    }
}
