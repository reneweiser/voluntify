<?php

use App\Livewire\Public\EmailVerificationPage;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create(['name' => 'Test Event']);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
    $this->volunteer = Volunteer::factory()->create();
});

it('shows success for valid token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $this->get(route('volunteer.verify-email', $plainToken))
        ->assertOk()
        ->assertSeeLivewire(EmailVerificationPage::class);

    // Verify the email was marked as verified
    expect($this->volunteer->fresh()->isEmailVerified())->toBeTrue();
});

it('shows success content via livewire', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true)
        ->assertSet('newSignupCount', 1)
        ->assertSet('skippedFullCount', 0)
        ->assertSee("You're Signed Up!");
});

it('shows expired message for expired token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$this->shift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->subHour(),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('expired', true)
        ->assertSee('Link Expired');
});

it('returns 404 for invalid token', function () {
    $this->get(route('volunteer.verify-email', 'nonexistent-token'))
        ->assertNotFound();
});

it('shows appropriate message when all shifts are full', function () {
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $otherVolunteer->id]);

    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'shift_ids' => [$tinyShift->id],
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true)
        ->assertSet('newSignupCount', 0)
        ->assertSee('Email Verified')
        ->assertSee('shifts are now full');
});
