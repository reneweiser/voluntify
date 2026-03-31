<?php

use App\Models\User;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
use Carbon\CarbonInterface;

it('belongs to volunteer gear', function () {
    $gear = VolunteerGear::factory()->create();
    $pickup = VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id]);

    expect($pickup->volunteerGear->id)->toBe($gear->id);
});

it('belongs to user via picked_up_by', function () {
    $user = User::factory()->create();
    $pickup = VolunteerGearPickup::factory()->create(['picked_up_by' => $user->id]);

    expect($pickup->pickedUpBy->id)->toBe($user->id);
});

it('casts picked_up_at to datetime', function () {
    $pickup = VolunteerGearPickup::factory()->create(['picked_up_at' => '2026-03-15 10:00:00']);

    expect($pickup->picked_up_at)->toBeInstanceOf(CarbonInterface::class);
});

it('casts quantity to integer', function () {
    $pickup = VolunteerGearPickup::factory()->create(['quantity' => 3]);

    expect($pickup->quantity)->toBe(3);
});

it('stores optional state field', function () {
    $pickup = VolunteerGearPickup::factory()->create(['state' => 'new']);

    expect($pickup->state)->toBe('new');
});

it('allows null state field', function () {
    $pickup = VolunteerGearPickup::factory()->create(['state' => null]);

    expect($pickup->state)->toBeNull();
});

it('defaults quantity to 1 in factory', function () {
    $pickup = VolunteerGearPickup::factory()->create();

    expect($pickup->quantity)->toBe(1);
});
