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

it('completes full wizard flow: select → reserve → info → confirm → verify → ticket', function () {
    // Step 1: Select shifts and reserve
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::PersonalInfo)
        // Step 2 skipped (no gear/custom fields)
        // Step 3: Personal info
        ->set('volunteerFirstName', 'Alice')
        ->set('volunteerLastName', 'Flow')
        ->set('volunteerEmail', 'alice@flow.test')
        ->set('volunteerPhone', '+1111111111')
        ->call('advanceToConfirmation')
        ->assertSet('state', WizardState::Confirming)
        // Step 4: Confirm & submit
        ->call('submitSignup')
        ->assertSet('state', WizardState::PendingVerification);

    // Volunteer created, email unverified
    $volunteer = Volunteer::where('email', 'alice@flow.test')->first();
    expect($volunteer)->not->toBeNull()
        ->and($volunteer->email_verified_at)->toBeNull();

    // Verification token created
    $token = EmailVerificationToken::where('volunteer_id', $volunteer->id)->first();
    expect($token)->not->toBeNull()
        ->and($token->event_id)->toBe($this->event->id)
        ->and($token->shift_ids)->toBe([$this->shift->id]);

    // Email verification
    $plainToken = Str::random(64);
    $token->update(['token_hash' => hash('sha256', $plainToken)]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true);

    // Volunteer is now verified and signed up
    $volunteer->refresh();
    expect($volunteer->email_verified_at)->not->toBeNull();

    // Shift signup created
    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $this->shift->id)->exists())->toBeTrue();

    // Ticket generated
    expect(Ticket::where('volunteer_id', $volunteer->id)->where('project_id', $this->project->id)->exists())->toBeTrue();

    // Magic link created
    $magicToken = $volunteer->magicLinkTokens()->first();
    expect($magicToken)->not->toBeNull();
});

it('completes signup immediately for verified volunteer through wizard', function () {
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create([
        'first_name' => 'Bob',
        'last_name' => 'Verified',
        'email' => 'bob@verified.test',
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::PersonalInfo)
        ->set('volunteerFirstName', 'Bob')
        ->set('volunteerLastName', 'Verified')
        ->set('volunteerEmail', 'bob@verified.test')
        ->call('advanceToConfirmation')
        ->call('submitSignup')
        ->assertSet('state', WizardState::Complete);

    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $this->shift->id)->exists())->toBeTrue();
    expect(Ticket::where('volunteer_id', $volunteer->id)->where('project_id', $this->project->id)->exists())->toBeTrue();
});

it('handles reservation expiry flow: reserve → expire → reset to step 1', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::PersonalInfo)
        ->assertNotSet('reservationExpiresAt', '')
        ->call('handleReservationExpired')
        ->assertSet('state', WizardState::Expired)
        ->call('restartSignup')
        ->assertSet('state', WizardState::SelectingShifts)
        ->assertSet('selectedShiftIds', []);

    // Reservations should still be in DB (cleanup is scheduled command's job)
    // but the wizard state is reset
});

it('releases reservations after successful signup', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'release@test.com',
        'first_name' => 'Release',
        'last_name' => 'Test',
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->set('volunteerFirstName', 'Release')
        ->set('volunteerLastName', 'Test')
        ->set('volunteerEmail', 'release@test.com')
        ->call('advanceToConfirmation')
        ->call('submitSignup')
        ->assertSet('state', WizardState::Complete);

    // Reservations should be cleaned up after successful signup
    expect(ShiftReservation::count())->toBe(0);
    expect(ShiftSignup::count())->toBe(1);
});
