<?php

use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\ValueObjects\SignupBatchResult;

it('reports has new signups when signups exist', function () {
    $volunteer = new Volunteer(['name' => 'Test', 'email' => 'test@test.com']);
    $signup = new ShiftSignup;

    $result = new SignupBatchResult(
        volunteer: $volunteer,
        newSignups: [$signup],
    );

    expect($result->hasNewSignups())->toBeTrue();
});

it('reports no new signups when empty', function () {
    $volunteer = new Volunteer(['name' => 'Test', 'email' => 'test@test.com']);

    $result = new SignupBatchResult(
        volunteer: $volunteer,
    );

    expect($result->hasNewSignups())->toBeFalse();
});

it('stores skipped shifts', function () {
    $volunteer = new Volunteer(['name' => 'Test', 'email' => 'test@test.com']);
    $fullShift = new Shift;
    $dupShift = new Shift;

    $result = new SignupBatchResult(
        volunteer: $volunteer,
        skippedFull: [$fullShift],
        skippedDuplicate: [$dupShift],
    );

    expect($result->skippedFull)->toHaveCount(1)
        ->and($result->skippedDuplicate)->toHaveCount(1)
        ->and($result->volunteer)->toBe($volunteer);
});
