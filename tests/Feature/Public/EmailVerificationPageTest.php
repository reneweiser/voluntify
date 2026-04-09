<?php

use App\Livewire\Public\EmailVerificationPage;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Test Event']);
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
});

it('shows verified message for valid token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true)
        ->assertSet('alreadyVerified', false)
        ->assertSee('Email Verified')
        ->assertSee('Continue Signup');

    expect($this->volunteer->fresh()->isEmailVerified())->toBeTrue();
});

it('shows already-verified message for re-used token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
        'verified_at' => now()->subMinutes(10),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('alreadyVerified', true)
        ->assertSet('verified', false)
        ->assertSee('Already Verified')
        ->assertSee('Continue Signup');
});

it('includes continue-signup link with token ID', function () {
    $plainToken = Str::random(64);
    $token = EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $component = Livewire::test(EmailVerificationPage::class, ['token' => $plainToken]);

    $continueUrl = $component->get('continueSignupUrl');
    expect($continueUrl)->toContain('vt='.$token->id)
        ->and($continueUrl)->toContain($this->event->public_token);
});

it('shows expired message for expired token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
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

it('handles token with null volunteer_id for new volunteer', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => null,
        'event_id' => $this->event->id,
        'email' => 'new@example.com',
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('verified', true)
        ->assertSee('Email Verified')
        ->assertSee('Continue Signup');
});

it('sets event name and public token from token', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken])
        ->assertSet('eventName', 'Test Event')
        ->assertSet('eventPublicToken', $this->event->public_token);
});

it('does not delete token after verification', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    Livewire::test(EmailVerificationPage::class, ['token' => $plainToken]);

    expect(EmailVerificationToken::count())->toBe(1);
});

it('renders via route', function () {
    $plainToken = Str::random(64);
    EmailVerificationToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'email' => $this->volunteer->email,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $this->get(route('volunteer.verify-email', $plainToken))
        ->assertOk()
        ->assertSeeLivewire(EmailVerificationPage::class);
});
