<?php

use App\Livewire\Public\EmailVerificationPage;
use App\Livewire\Public\EventSignup;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);
});

it('completes full signup flow: signup → verify → ticket', function () {
    // Step 1: Volunteer signs up (unverified email)
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Alice Flow')
        ->set('volunteerEmail', 'alice@flow.test')
        ->set('volunteerPhone', '+1111111111')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertSet('pendingVerification', true);

    // Volunteer created, email unverified
    $volunteer = Volunteer::where('email', 'alice@flow.test')->first();
    expect($volunteer)->not->toBeNull()
        ->and($volunteer->email_verified_at)->toBeNull();

    // Verification token created
    $token = EmailVerificationToken::where('volunteer_id', $volunteer->id)->first();
    expect($token)->not->toBeNull()
        ->and($token->event_id)->toBe($this->event->id)
        ->and($token->shift_ids)->toBe([$this->shift->id]);

    // Step 2: Email verification
    // Replace token hash with one we control
    $plainToken = \Illuminate\Support\Str::random(64);
    $token->update(['token_hash' => hash('sha256', $plainToken)]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true);

    // Volunteer is now verified and signed up
    $volunteer->refresh();
    expect($volunteer->email_verified_at)->not->toBeNull();

    // Shift signup created
    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $this->shift->id)->exists())->toBeTrue();

    // Ticket generated
    expect(Ticket::where('volunteer_id', $volunteer->id)->where('event_id', $this->event->id)->exists())->toBeTrue();

    // Step 3: Ticket access via magic link
    $magicToken = $volunteer->magicLinkTokens()->first();
    expect($magicToken)->not->toBeNull();
});

it('completes signup immediately for verified volunteer', function () {
    // Pre-create a verified volunteer
    $volunteer = Volunteer::factory()->verified()->create([
        'name' => 'Bob Verified',
        'email' => 'bob@verified.test',
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Bob Verified')
        ->set('volunteerEmail', 'bob@verified.test')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertSet('signupComplete', true)
        ->assertSet('pendingVerification', false);

    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $this->shift->id)->exists())->toBeTrue();
    expect(Ticket::where('volunteer_id', $volunteer->id)->where('event_id', $this->event->id)->exists())->toBeTrue();
});
