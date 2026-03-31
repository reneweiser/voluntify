<?php

use App\Enums\SignupOutcomeType;
use App\Models\Volunteer;
use App\ValueObjects\ShiftSignupResult;
use App\ValueObjects\SignupOutcome;

it('creates a completed outcome with batch result', function () {
    $volunteer = new Volunteer(['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com']);
    $batchResult = new ShiftSignupResult(volunteer: $volunteer);

    $outcome = SignupOutcome::completed($batchResult);

    expect($outcome->type)->toBe(SignupOutcomeType::Completed)
        ->and($outcome->batchResult)->toBe($batchResult)
        ->and($outcome->pendingEmail)->toBeNull()
        ->and($outcome->isPendingVerification())->toBeFalse();
});

it('creates a pending verification outcome with email', function () {
    $outcome = SignupOutcome::pendingVerification('test@example.com');

    expect($outcome->type)->toBe(SignupOutcomeType::PendingVerification)
        ->and($outcome->pendingEmail)->toBe('test@example.com')
        ->and($outcome->batchResult)->toBeNull()
        ->and($outcome->isPendingVerification())->toBeTrue();
});
