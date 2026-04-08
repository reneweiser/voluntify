<?php

use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;

it('stores quantity_entitled on volunteer gear', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => 5]);

    expect($gear->fresh()->quantity_entitled)->toBe(5);
});

it('defaults quantity_entitled to null', function () {
    $gear = VolunteerGear::factory()->create();

    expect($gear->quantity_entitled)->toBeNull();
});

it('computes totalPickedUp as sum of pickup quantities', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => 5]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 2]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    expect($gear->totalPickedUp())->toBe(4);
});

it('returns zero totalPickedUp when no pickups exist', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => 3]);

    expect($gear->totalPickedUp())->toBe(0);
});

it('uses loaded relation for totalPickedUp when available', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => 5]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 2]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 3]);

    $gearWithPickups = VolunteerGear::with('pickups')->find($gear->id);

    expect($gearWithPickups->totalPickedUp())->toBe(5);
});

it('computes remainingQuantity correctly', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => 5]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 2]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    expect($gear->remainingQuantity())->toBe(2);
});

it('returns null remainingQuantity for Typ 1 gear', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => null]);

    expect($gear->remainingQuantity())->toBeNull();
});

it('returns zero remainingQuantity when fully picked up', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => 3]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 2]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    expect($gear->remainingQuantity())->toBe(0);
});

it('isPickedUp still works for Typ 1 gear', function () {
    $gear = VolunteerGear::factory()->create(['quantity_entitled' => null]);

    expect($gear->isPickedUp())->toBeFalse();

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id]);

    expect($gear->isPickedUp())->toBeTrue();
});

it('factory withQuantity state sets quantity_entitled', function () {
    $gear = VolunteerGear::factory()->withQuantity(5)->create();

    expect($gear->quantity_entitled)->toBe(5);
});
