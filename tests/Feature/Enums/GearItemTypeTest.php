<?php

use App\Enums\GearItemType;

it('has size selection type', function () {
    expect(GearItemType::SizeSelection->value)->toBe('size_selection')
        ->and(GearItemType::SizeSelection->label())->toBe('Size Selection');
});

it('has quantity type', function () {
    expect(GearItemType::Quantity->value)->toBe('quantity')
        ->and(GearItemType::Quantity->label())->toBe('Quantity');
});

it('has exactly two cases', function () {
    expect(GearItemType::cases())->toHaveCount(2);
});
