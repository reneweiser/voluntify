<?php

use App\Models\ShiftSignup;
use App\Models\Volunteer;

it('backfills email verification from the earliest shift signup timestamp', function () {
    $verifiedAt = now()->subDays(3);
    $earliestSignupAt = now()->subDays(10)->startOfMinute();
    $latestSignupAt = now()->subDays(5)->startOfMinute();

    $volunteer = Volunteer::factory()->create([
        'email_verified_at' => null,
    ]);

    ShiftSignup::factory()->for($volunteer)->create([
        'signed_up_at' => $latestSignupAt,
    ]);
    ShiftSignup::factory()->for($volunteer)->create([
        'signed_up_at' => $earliestSignupAt,
    ]);

    $alreadyVerifiedVolunteer = Volunteer::factory()->verified()->create([
        'email_verified_at' => $verifiedAt,
    ]);

    ShiftSignup::factory()->for($alreadyVerifiedVolunteer)->create([
        'signed_up_at' => now()->subDays(20),
    ]);

    $volunteerWithoutSignups = Volunteer::factory()->create([
        'email_verified_at' => null,
    ]);

    $migration = require base_path('database/migrations/2026_04_28_165639_backfill_email_verified_at_for_volunteers_with_shift_signups.php');
    $migration->up();

    expect($volunteer->fresh()->email_verified_at?->toDateTimeString())
        ->toBe($earliestSignupAt->toDateTimeString())
        ->and($alreadyVerifiedVolunteer->fresh()->email_verified_at?->toDateTimeString())
        ->toBe($verifiedAt->toDateTimeString())
        ->and($volunteerWithoutSignups->fresh()->email_verified_at)
        ->toBeNull();
});
