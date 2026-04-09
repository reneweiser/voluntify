<?php

use App\Enums\WizardState;
use App\Livewire\Public\EmailVerificationPage;
use App\Livewire\Public\EventSignup;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);
});

it('completes full flow: email → verify → info → shifts → confirm → complete', function () {
    // Step 1: Email entry
    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSet('state', WizardState::EmailEntry)
        ->set('volunteerEmail', 'alice@flow.test')
        ->call('submitEmail')
        ->assertSet('state', WizardState::PendingVerification);

    // Verification token was created
    $token = EmailVerificationToken::first();
    expect($token)->not->toBeNull()
        ->and($token->email)->toBe('alice@flow.test')
        ->and($token->shift_ids)->toBeNull();

    // Simulate clicking the verification link (marks token as verified)
    $plainToken = Str::random(64);
    $token->update(['token_hash' => HashedToken::fromPlaintext($plainToken)->hash]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true);

    // Polling detects verification and advances
    $component->call('checkVerification')
        ->assertSet('state', WizardState::PersonalInfo);

    // Step 2: Personal info
    $component
        ->set('volunteerFirstName', 'Alice')
        ->set('volunteerLastName', 'Flow')
        ->set('volunteerPhone', '+1111111111')
        ->call('advanceToShifts')
        ->assertSet('state', WizardState::SelectingShifts);

    // Step 3: Select shifts and reserve
    $component
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming);

    // Step 4: Confirm & submit
    $component
        ->call('submitSignup')
        ->assertSet('state', WizardState::Complete);

    // Volunteer created and signed up
    $volunteer = Volunteer::where('email', 'alice@flow.test')->first();
    expect($volunteer)->not->toBeNull()
        ->and($volunteer->first_name)->toBe('Alice')
        ->and($volunteer->last_name)->toBe('Flow');

    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $this->shift->id)->exists())->toBeTrue();
    expect(Ticket::where('volunteer_id', $volunteer->id)->where('project_id', $this->project->id)->exists())->toBeTrue();
});

it('handles returning verified volunteer: email → verify → info (prefilled) → shifts → confirm', function () {
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create([
        'first_name' => 'Bob',
        'last_name' => 'Verified',
        'email' => 'bob@verified.test',
        'phone' => '+2222222222',
    ]);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSet('state', WizardState::EmailEntry)
        ->set('volunteerEmail', 'bob@verified.test')
        ->call('submitEmail')
        ->assertSet('state', WizardState::PendingVerification);

    // Simulate verification
    $token = EmailVerificationToken::where('email', 'bob@verified.test')->first();
    $token->update(['verified_at' => now()]);
    $component->call('checkVerification')
        ->assertSet('state', WizardState::PersonalInfo)
        ->assertSet('volunteerFirstName', 'Bob')
        ->assertSet('volunteerLastName', 'Verified')
        ->assertSet('volunteerPhone', '+2222222222')
        ->assertSet('isReturningVolunteer', true)
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming)
        ->call('submitSignup')
        ->assertSet('state', WizardState::Complete);

    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $this->shift->id)->exists())->toBeTrue();
    expect(Ticket::where('volunteer_id', $volunteer->id)->where('project_id', $this->project->id)->exists())->toBeTrue();
});

it('only reserves shifts at SelectingShifts step, not before', function () {
    // Enter email and verify
    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'reserve@test.com')
        ->call('submitEmail');

    // No reservations yet at PendingVerification
    expect(ShiftReservation::count())->toBe(0);

    // Simulate verification
    $token = EmailVerificationToken::first();
    $token->update(['verified_at' => now()]);
    $component->call('checkVerification');

    // No reservations yet at PersonalInfo
    expect(ShiftReservation::count())->toBe(0);

    // Fill personal info
    $component
        ->set('volunteerFirstName', 'Reserve')
        ->set('volunteerLastName', 'Test')
        ->call('advanceToShifts');

    // No reservations yet at SelectingShifts (before reserveAndAdvance)
    expect(ShiftReservation::count())->toBe(0);

    // Reserve
    $component
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance');

    // NOW reservations exist
    expect(ShiftReservation::count())->toBe(1);
});

it('handles reservation expiry flow: reserve → expire → restart to EmailEntry', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'expire@test.com',
        'first_name' => 'Expire',
        'last_name' => 'Test',
    ]);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'expire@test.com')
        ->call('submitEmail');

    $token = EmailVerificationToken::where('email', 'expire@test.com')->first();
    $token->update(['verified_at' => now()]);
    $component->call('checkVerification')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming)
        ->call('handleReservationExpired')
        ->assertSet('state', WizardState::Expired)
        ->call('restartSignup')
        ->assertSet('state', WizardState::EmailEntry)
        ->assertSet('selectedShiftIds', []);
});

it('releases reservations after successful signup', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'release@test.com',
        'first_name' => 'Release',
        'last_name' => 'Test',
    ]);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'release@test.com')
        ->call('submitEmail');

    $token = EmailVerificationToken::where('email', 'release@test.com')->first();
    $token->update(['verified_at' => now()]);
    $component->call('checkVerification')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming)
        ->call('submitSignup')
        ->assertSet('state', WizardState::Complete);

    expect(ShiftReservation::count())->toBe(0);
    expect(ShiftSignup::count())->toBe(1);
});

it('handles re-used verification link gracefully', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => Volunteer::factory()->for($this->project)->create()->id,
        'event_id' => $this->event->id,
        'email' => 'reuse@test.com',
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
        'verified_at' => now()->subMinutes(10),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('alreadyVerified', true)
        ->assertSee('Already Verified')
        ->assertSee('Continue Signup');
});

it('handles cross-device verification via vt query param', function () {
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'crossdevice@test.com',
        'first_name' => 'Cross',
        'last_name' => 'Device',
    ]);

    $token = EmailVerificationToken::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'email' => 'crossdevice@test.com',
        'verified_at' => now()->subMinutes(5),
        'expires_at' => now()->addHours(24),
    ]);

    // Verify cross-device access via full HTTP request with ?vt= query param (uses token_hash)
    $url = route('events.public', $this->event->public_token).'?vt='.$token->token_hash;
    $this->get($url)
        ->assertOk()
        ->assertSeeLivewire(EventSignup::class);
});

it('stops polling after token expires and shows resend option', function () {
    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'expire-poll@test.com')
        ->call('submitEmail')
        ->assertSet('state', WizardState::PendingVerification);

    // Expire the token
    $token = EmailVerificationToken::first();
    $token->update(['expires_at' => now()->subMinute()]);

    // Polling should detect expiry
    $component->call('checkVerification');

    // Token ID should be cleared
    expect($component->get('verificationTokenId'))->toBeNull();
});
