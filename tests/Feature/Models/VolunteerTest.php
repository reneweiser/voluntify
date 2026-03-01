<?php

use App\Models\EventArrival;
use App\Models\MagicLinkToken;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;

it('has unique email constraint', function () {
    Volunteer::factory()->create(['email' => 'volunteer@example.com']);

    expect(fn () => Volunteer::factory()->create(['email' => 'volunteer@example.com']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('has nullable user relationship', function () {
    $volunteer = Volunteer::factory()->create(['user_id' => null]);
    expect($volunteer->user)->toBeNull();

    $user = User::factory()->create();
    $linked = Volunteer::factory()->create(['user_id' => $user->id]);
    expect($linked->user->id)->toBe($user->id);
});

it('has many shift signups', function () {
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->count(2)->for($volunteer)->create();

    expect($volunteer->shiftSignups)->toHaveCount(2);
});

it('has many tickets', function () {
    $volunteer = Volunteer::factory()->create();
    Ticket::factory()->count(2)->for($volunteer)->create();

    expect($volunteer->tickets)->toHaveCount(2);
});

it('has many event arrivals', function () {
    $volunteer = Volunteer::factory()->create();
    EventArrival::factory()->create(['volunteer_id' => $volunteer->id]);

    expect($volunteer->eventArrivals)->toHaveCount(1);
});

it('has many magic link tokens', function () {
    $volunteer = Volunteer::factory()->create();
    MagicLinkToken::factory()->count(2)->for($volunteer)->create();

    expect($volunteer->magicLinkTokens)->toHaveCount(2);
});

it('has one promotion', function () {
    $volunteer = Volunteer::factory()->create();
    VolunteerPromotion::factory()->for($volunteer)->create();

    expect($volunteer->promotion)->toBeInstanceOf(VolunteerPromotion::class);
});
