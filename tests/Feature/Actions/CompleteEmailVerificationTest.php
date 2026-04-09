<?php

use App\Actions\CompleteEmailVerification;
use App\Events\Activity\VolunteerVerified;
use App\Exceptions\DomainException;
use App\Exceptions\ExpiredVerificationException;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;

beforeEach(function () {
    EventFacade::fake([VolunteerVerified::class]);

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
});

it('sets verified_at on token instead of deleting it', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $result = $action->execute($plainToken);

    expect($result)->toBeInstanceOf(EmailVerificationToken::class)
        ->and($result->isVerified())->toBeTrue()
        ->and(EmailVerificationToken::count())->toBe(1);
});

it('returns token when already verified without throwing exception', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
        'verified_at' => now()->subMinutes(5),
    ]);

    $action = app(CompleteEmailVerification::class);
    $result = $action->execute($plainToken);

    expect($result)->toBeInstanceOf(EmailVerificationToken::class)
        ->and($result->isVerified())->toBeTrue();
});

it('does not create shift signups', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'shift_ids' => [$this->shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    expect(ShiftSignup::count())->toBe(0);
});

it('marks volunteer email as verified when volunteer_id is set', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    expect($this->volunteer->fresh()->isEmailVerified())->toBeTrue();
});

it('does not mark email as verified when volunteer_id is null', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => null,
        'event_id' => $this->event->id,
        'email' => 'new@example.com',
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $result = $action->execute($plainToken);

    expect($result->isVerified())->toBeTrue()
        ->and($result->volunteer_id)->toBeNull();
});

it('dispatches VolunteerVerified event when volunteer exists', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    EventFacade::assertDispatched(VolunteerVerified::class);
});

it('does not dispatch VolunteerVerified for already-verified token', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
        'verified_at' => now()->subMinutes(5),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    EventFacade::assertNotDispatched(VolunteerVerified::class);
});

it('throws ExpiredVerificationException for expired token', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->subHour(),
    ]);

    $action = app(CompleteEmailVerification::class);

    expect(fn () => $action->execute($plainToken))->toThrow(ExpiredVerificationException::class);
});

it('throws ModelNotFoundException for invalid token', function () {
    $action = app(CompleteEmailVerification::class);

    expect(fn () => $action->execute('invalid-token'))->toThrow(ModelNotFoundException::class);
});

it('throws DomainException for archived event', function () {
    $archivedEvent = Event::factory()->for($this->org)->for($this->project)->archived()->create();

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $archivedEvent->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);

    expect(fn () => $action->execute($plainToken))->toThrow(DomainException::class);
});

it('handles legacy tokens with non-null shift_ids gracefully', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'shift_ids' => [$this->shift->id],
        'gear_selections' => [1 => 'L'],
        'custom_field_responses' => [42 => 'Vegan'],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $result = $action->execute($plainToken);

    // Still works — just verifies, doesn't process shifts/gear/custom fields
    expect($result->isVerified())->toBeTrue()
        ->and($this->volunteer->fresh()->isEmailVerified())->toBeTrue()
        ->and(ShiftSignup::count())->toBe(0);
});
