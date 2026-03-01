<?php

use App\Enums\ArrivalMethod;

it('has exactly two cases', function () {
    expect(ArrivalMethod::cases())->toHaveCount(2);
});

it('has the correct backing values', function (ArrivalMethod $case, string $value) {
    expect($case->value)->toBe($value);
})->with([
    [ArrivalMethod::QrScan, 'qr_scan'],
    [ArrivalMethod::ManualLookup, 'manual_lookup'],
]);
