<?php

use App\Actions\ProcessVolunteerSignup;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;
use App\Notifications\EmailVerification;
use App\Notifications\SignupConfirmation;
use App\ValueObjects\HashedToken;
use App\ValueObjects\ShiftSignupResult;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
});

it('always returns ShiftSignupResult directly', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com']);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Test',
        lastName: 'Person',
        email: 'test@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect($result)->toBeInstanceOf(ShiftSignupResult::class)
        ->and($result->hasNewSignups())->toBeTrue();
});

it('never sends verification email', function () {
    $action = app(ProcessVolunteerSignup::class);

    $action->execute(
        firstName: 'New',
        lastName: 'Person',
        email: 'new@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    Notification::assertNotSentTo(
        Volunteer::where('email', 'new@example.com')->first(),
        EmailVerification::class,
    );

    expect(EmailVerificationToken::count())->toBe(0);
});

it('creates volunteer and signups for new volunteer', function () {
    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'New',
        lastName: 'Person',
        email: 'new@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and(Volunteer::where('email', 'new@example.com')->exists())->toBeTrue()
        ->and(ShiftSignup::count())->toBe(1)
        ->and(Ticket::count())->toBe(1);

    Notification::assertSentTo(
        Volunteer::where('email', 'new@example.com')->first(),
        SignupConfirmation::class,
    );
});

it('marks a newly created volunteer as verified when a matching email token was already verified', function () {
    EmailVerificationToken::factory()->create([
        'event_id' => $this->event->id,
        'project_id' => $this->project->id,
        'email' => 'verified-new@example.com',
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subMinute(),
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $action->execute(
        firstName: 'Verified',
        lastName: 'New',
        email: 'verified-new@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect(Volunteer::where('email', 'verified-new@example.com')->first())
        ->not->toBeNull()
        ->email_verified_at->not->toBeNull();
});

it('reuses verification from another event in the same project', function () {
    $otherEvent = Event::factory()->for($this->org)->for($this->project)->published()->create();

    EmailVerificationToken::factory()->create([
        'event_id' => $otherEvent->id,
        'project_id' => $this->project->id,
        'email' => 'cross-event@example.com',
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subMinute(),
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $action->execute(
        firstName: 'Cross',
        lastName: 'Event',
        email: 'cross-event@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect(Volunteer::where('email', 'cross-event@example.com')->first())
        ->not->toBeNull()
        ->email_verified_at->not->toBeNull();
});

it('completes signup for verified returning volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'verified@example.com',
        'first_name' => 'Verified',
        'last_name' => 'Person',
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Verified',
        lastName: 'Person',
        email: 'verified@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and(ShiftSignup::count())->toBe(1)
        ->and(Ticket::count())->toBe(1);

    Notification::assertSentTo(
        Volunteer::where('email', 'verified@example.com')->first(),
        SignupConfirmation::class,
    );
});

it('creates gear records with gear selections', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'gear@example.com']);

    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $badge = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Gear',
        lastName: 'Person',
        email: 'gear@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        gearSelections: [$tshirt->id => 'M', $badge->id => null],
    );

    expect($result)->toBeInstanceOf(ShiftSignupResult::class);
    expect(VolunteerGear::count())->toBe(2);

    $tshirtGear = VolunteerGear::where('project_gear_item_id', $tshirt->id)->first();
    expect($tshirtGear->size)->toBe('M');
});

it('records custom field responses', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'custom@example.com']);

    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Custom',
        lastName: 'Person',
        email: 'custom@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        customFieldResponses: [$field->id => 'Vegan'],
    );

    expect($result)->toBeInstanceOf(ShiftSignupResult::class);
    expect(CustomFieldResponse::count())->toBe(1);
    expect(CustomFieldResponse::first()->value)->toBe('Vegan');
});

it('updates phone number for existing volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'test@example.com',
        'phone' => null,
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $action->execute(
        firstName: 'Test',
        lastName: 'User',
        email: 'test@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        phone: '+15551234567',
    );

    expect(Volunteer::where('email', 'test@example.com')->first()->phone)->toBe('+15551234567');
});

it('passes sessionId through to release reservations', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'session@example.com']);

    ShiftReservation::factory()->create([
        'shift_id' => $this->shift->id,
        'session_id' => 'signup-session',
        'expires_at' => now()->addMinutes(10),
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Session',
        lastName: 'Test',
        email: 'session@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        sessionId: 'signup-session',
    );

    expect($result->hasNewSignups())->toBeTrue();
    expect(ShiftReservation::forSession('signup-session')->count())->toBe(0);
});

it('auto-assigns Typ 2 gear without gearSelections', function () {
    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Drinks']);

    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'typ2@example.com',
        'first_name' => 'Auto',
        'last_name' => 'Gear',
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Auto',
        lastName: 'Gear',
        email: 'typ2@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect($result)->toBeInstanceOf(ShiftSignupResult::class);
    expect(VolunteerGear::count())->toBe(1);

    $gear = VolunteerGear::first();
    expect($gear->project_gear_item_id)->toBe($drinks->id)
        ->and($gear->quantity_entitled)->toBe(3);
});

it('auto-assigns Typ 2 gear alongside Typ 1 selections', function () {
    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $drinks = ProjectGearItem::factory()->quantity(2)->for($this->project)->create(['name' => 'Drinks']);

    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'both@example.com',
        'first_name' => 'Both',
        'last_name' => 'Types',
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $result = $action->execute(
        firstName: 'Both',
        lastName: 'Types',
        email: 'both@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        gearSelections: [$tshirt->id => 'L'],
    );

    expect($result)->toBeInstanceOf(ShiftSignupResult::class);
    expect(VolunteerGear::count())->toBe(2);

    expect(VolunteerGear::where('project_gear_item_id', $tshirt->id)->first()->size)->toBe('L');
    expect(VolunteerGear::where('project_gear_item_id', $drinks->id)->first()->quantity_entitled)->toBe(2);
});
