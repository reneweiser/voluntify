<?php

use App\Actions\RecordGuestGearPickup;
use App\Exceptions\DomainException;
use App\Models\GuestEntryGear;

it('sets selection and status for Typ-1 gear', function () {
    $gear = GuestEntryGear::factory()->create([
        'quantity' => 1,
        'selection' => null,
        'status' => null,
    ]);

    $action = new RecordGuestGearPickup;
    $result = $action->execute($gear, ['selection' => 'L', 'status' => 'issued']);

    expect($result->selection)->toBe('L')
        ->and($result->status)->toBe('issued');
});

it('increments picked_up_count for Typ-2 gear', function () {
    $gear = GuestEntryGear::factory()->create([
        'quantity' => 3,
        'picked_up_count' => 1,
    ]);

    $action = new RecordGuestGearPickup;
    $result = $action->execute($gear, ['quantity' => 1]);

    expect($result->picked_up_count)->toBe(2);
});

it('rejects pickup that would exceed quantity', function () {
    $gear = GuestEntryGear::factory()->create([
        'quantity' => 3,
        'picked_up_count' => 3,
    ]);

    $action = new RecordGuestGearPickup;

    expect(fn () => $action->execute($gear, ['quantity' => 1]))
        ->toThrow(DomainException::class, 'Pickup count would exceed available quantity.');
});

it('allows pickup at exact boundary', function () {
    $gear = GuestEntryGear::factory()->create([
        'quantity' => 3,
        'picked_up_count' => 2,
    ]);

    $action = new RecordGuestGearPickup;
    $result = $action->execute($gear, ['quantity' => 1]);

    expect($result->picked_up_count)->toBe(3);
});

it('handles update with no data gracefully', function () {
    $gear = GuestEntryGear::factory()->create([
        'quantity' => 2,
        'picked_up_count' => 0,
        'selection' => null,
        'status' => null,
    ]);

    $action = new RecordGuestGearPickup;
    $result = $action->execute($gear, []);

    expect($result->picked_up_count)->toBe(0)
        ->and($result->selection)->toBeNull()
        ->and($result->status)->toBeNull();
});
