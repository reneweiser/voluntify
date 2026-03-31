<?php

use App\Actions\RecordGearPickup;
use App\Models\User;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;

it('creates a pickup record for gear', function () {
    $user = User::factory()->create();
    $gear = VolunteerGear::factory()->create();

    $action = new RecordGearPickup;
    $pickup = $action->execute($gear, $user);

    expect($pickup)->toBeInstanceOf(VolunteerGearPickup::class)
        ->and($pickup->volunteer_gear_id)->toBe($gear->id)
        ->and($pickup->picked_up_by)->toBe($user->id)
        ->and($pickup->picked_up_at)->not->toBeNull()
        ->and($pickup->quantity)->toBe(1)
        ->and($pickup->state)->toBeNull();
});

it('records pickup with custom state and quantity', function () {
    $user = User::factory()->create();
    $gear = VolunteerGear::factory()->create();

    $action = new RecordGearPickup;
    $pickup = $action->execute($gear, $user, 'new', 3);

    expect($pickup->state)->toBe('new')
        ->and($pickup->quantity)->toBe(3);
});

it('allows multiple pickups for the same gear', function () {
    $user = User::factory()->create();
    $gear = VolunteerGear::factory()->create();

    $action = new RecordGearPickup;
    $action->execute($gear, $user);
    $action->execute($gear, $user);

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(2);
});

it('marks gear as picked up after recording', function () {
    $user = User::factory()->create();
    $gear = VolunteerGear::factory()->create();

    expect($gear->isPickedUp())->toBeFalse();

    $action = new RecordGearPickup;
    $action->execute($gear, $user);

    expect($gear->isPickedUp())->toBeTrue();
});
