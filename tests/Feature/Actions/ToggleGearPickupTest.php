<?php

use App\Actions\ToggleGearPickup;
use App\Models\User;
use App\Models\VolunteerGear;

it('marks gear as picked up', function () {
    $user = User::factory()->create();
    $gear = VolunteerGear::factory()->create();

    $action = new ToggleGearPickup;
    $action->execute($gear, $user);

    $gear->refresh();

    expect($gear->picked_up_at)->not->toBeNull()
        ->and($gear->picked_up_by)->toBe($user->id);
});

it('unmarks gear pickup when already picked up', function () {
    $user = User::factory()->create();
    $gear = VolunteerGear::factory()->pickedUp()->create();

    expect($gear->picked_up_at)->not->toBeNull();

    $action = new ToggleGearPickup;
    $action->execute($gear, $user);

    $gear->refresh();

    expect($gear->picked_up_at)->toBeNull()
        ->and($gear->picked_up_by)->toBeNull();
});
