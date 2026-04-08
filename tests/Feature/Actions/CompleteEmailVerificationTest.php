<?php

use App\Actions\CompleteEmailVerification;
use App\Exceptions\DomainException;
use App\Exceptions\ExpiredVerificationException;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
});

it('verifies email and creates signups for valid token', function () {
    $plainToken = Str::random(64);

    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $result = $action->execute($plainToken);

    // Email marked as verified
    expect($this->volunteer->fresh()->isEmailVerified())->toBeTrue();

    // Signups and ticket created
    expect($result->hasNewSignups())->toBeTrue()
        ->and(ShiftSignup::count())->toBe(1)
        ->and(Ticket::count())->toBe(1);

    // Token deleted
    expect(EmailVerificationToken::count())->toBe(0);

    // SignupConfirmation sent
    Notification::assertSentTo($this->volunteer, SignupConfirmation::class);
});

it('reports skipped full shifts when shifts fill before verification', function () {
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $otherVolunteer->id]);

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$tinyShift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $result = $action->execute($plainToken);

    // Email still verified even though shifts are full
    expect($this->volunteer->fresh()->isEmailVerified())->toBeTrue()
        ->and(count($result->skippedFull))->toBe(1)
        ->and($result->hasNewSignups())->toBeFalse();
});

it('deletes token on first use making second click fail', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    // Second attempt should fail
    expect(fn () => $action->execute($plainToken))->toThrow(ModelNotFoundException::class);
});

it('throws ExpiredVerificationException for expired token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
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

it('creates gear records from token gear selections on verification', function () {
    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $badge = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'gear_selections' => [$tshirt->id => 'L', $badge->id => null],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    expect(VolunteerGear::count())->toBe(2);
    expect(VolunteerGear::where('project_gear_item_id', $tshirt->id)->first()->size)->toBe('L');
    expect(VolunteerGear::where('project_gear_item_id', $badge->id)->first()->size)->toBeNull();
});

it('creates custom field responses from token on verification', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'custom_field_responses' => [$field->id => 'Vegan'],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    expect(CustomFieldResponse::count())->toBe(1);
    expect(CustomFieldResponse::first()->value)->toBe('Vegan');
});

it('throws DomainException for archived event', function () {
    $archivedEvent = Event::factory()->for($this->org)->for($this->project)->archived()->create();
    $job = VolunteerJob::factory()->for($archivedEvent)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 10]);

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $archivedEvent->id,
        'shift_ids' => [$shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);

    expect(fn () => $action->execute($plainToken))->toThrow(DomainException::class);
});

it('auto-assigns Typ 2 gear on verification without gear_selections on token', function () {
    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Drinks']);

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'gear_selections' => null,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $action = app(CompleteEmailVerification::class);
    $action->execute($plainToken);

    expect(VolunteerGear::count())->toBe(1);

    $gear = VolunteerGear::first();
    expect($gear->project_gear_item_id)->toBe($drinks->id)
        ->and($gear->quantity_entitled)->toBe(3);
});
