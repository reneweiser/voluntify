<?php

use App\Actions\ProcessVolunteerSignup;
use App\Enums\SignupOutcomeType;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\EmailVerification;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
});

it('sends verification email for unverified new volunteer', function () {
    $action = app(ProcessVolunteerSignup::class);

    $outcome = $action->execute(
        name: 'New Person',
        email: 'new@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect($outcome->type)->toBe(SignupOutcomeType::PendingVerification)
        ->and($outcome->pendingEmail)->toBe('new@example.com')
        ->and($outcome->isPendingVerification())->toBeTrue();

    // Volunteer created but no signups or tickets
    expect(Volunteer::where('email', 'new@example.com')->exists())->toBeTrue()
        ->and(ShiftSignup::count())->toBe(0)
        ->and(Ticket::count())->toBe(0);

    // Verification token created
    expect(EmailVerificationToken::count())->toBe(1);

    // EmailVerification sent, not SignupConfirmation
    Notification::assertSentTo(
        Volunteer::where('email', 'new@example.com')->first(),
        EmailVerification::class,
    );
    Notification::assertNotSentTo(
        Volunteer::where('email', 'new@example.com')->first(),
        SignupConfirmation::class,
    );
});

it('completes signup immediately for verified returning volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'verified@example.com', 'name' => 'Verified Person']);

    $action = app(ProcessVolunteerSignup::class);

    $outcome = $action->execute(
        name: 'Verified Person',
        email: 'verified@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
    );

    expect($outcome->type)->toBe(SignupOutcomeType::Completed)
        ->and($outcome->batchResult)->not->toBeNull()
        ->and($outcome->batchResult->hasNewSignups())->toBeTrue();

    // Signups and ticket created
    expect(ShiftSignup::count())->toBe(1)
        ->and(Ticket::count())->toBe(1);

    // SignupConfirmation sent, not EmailVerification
    Notification::assertSentTo(
        Volunteer::where('email', 'verified@example.com')->first(),
        SignupConfirmation::class,
    );
    Notification::assertNotSentTo(
        Volunteer::where('email', 'verified@example.com')->first(),
        EmailVerification::class,
    );
});

it('skips verification for returning verified volunteer on new event', function () {
    $volunteer = Volunteer::factory()->verified()->create();

    $newEvent = Event::factory()->for($this->org)->published()->create();
    $newJob = VolunteerJob::factory()->for($newEvent)->create();
    $newShift = Shift::factory()->for($newJob, 'volunteerJob')->create(['capacity' => 10]);

    $action = app(ProcessVolunteerSignup::class);

    $outcome = $action->execute(
        name: $volunteer->name,
        email: $volunteer->email,
        event: $newEvent,
        shiftIds: [$newShift->id],
    );

    expect($outcome->type)->toBe(SignupOutcomeType::Completed)
        ->and($outcome->batchResult->hasNewSignups())->toBeTrue();

    Notification::assertSentTo($volunteer, SignupConfirmation::class);
});

it('creates gear records for verified volunteer with gear selections', function () {
    Volunteer::factory()->verified()->create(['email' => 'gear@example.com']);

    $tshirt = \App\Models\EventGearItem::factory()->sized()->for($this->event)->create(['name' => 'T-Shirt']);
    $badge = \App\Models\EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    $action = app(ProcessVolunteerSignup::class);

    $outcome = $action->execute(
        name: 'Gear Person',
        email: 'gear@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        phone: null,
        gearSelections: [$tshirt->id => 'M'],
    );

    expect($outcome->type)->toBe(\App\Enums\SignupOutcomeType::Completed);
    expect(\App\Models\VolunteerGear::count())->toBe(2);

    $tshirtGear = \App\Models\VolunteerGear::where('event_gear_item_id', $tshirt->id)->first();
    expect($tshirtGear->size)->toBe('M');
});

it('stores gear selections on verification token for unverified volunteer', function () {
    $tshirt = \App\Models\EventGearItem::factory()->sized()->for($this->event)->create(['name' => 'T-Shirt']);

    $action = app(ProcessVolunteerSignup::class);

    $action->execute(
        name: 'Unverified Gear',
        email: 'unverified-gear@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        phone: null,
        gearSelections: [$tshirt->id => 'L'],
    );

    $token = EmailVerificationToken::first();
    expect($token->gear_selections)->toBe([$tshirt->id => 'L']);
    expect(\App\Models\VolunteerGear::count())->toBe(0);
});

it('updates phone number for existing volunteer', function () {
    Volunteer::factory()->verified()->create([
        'email' => 'test@example.com',
        'phone' => null,
    ]);

    $action = app(ProcessVolunteerSignup::class);

    $action->execute(
        name: 'Test',
        email: 'test@example.com',
        event: $this->event,
        shiftIds: [$this->shift->id],
        phone: '+15551234567',
    );

    expect(Volunteer::where('email', 'test@example.com')->first()->phone)->toBe('+15551234567');
});
