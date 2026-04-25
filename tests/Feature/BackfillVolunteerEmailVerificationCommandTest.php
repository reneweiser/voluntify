<?php

use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
});

it('backfills volunteer email_verified_at from matching verified tokens', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'backfill@example.com',
        'email_verified_at' => null,
    ]);

    EmailVerificationToken::factory()->create([
        'event_id' => $this->event->id,
        'project_id' => $this->project->id,
        'email' => $volunteer->email,
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subHour(),
    ]);

    $this->artisan('app:backfill-volunteer-email-verification')
        ->expectsOutput('Backfilled 1 volunteer(s).')
        ->assertSuccessful();

    expect($volunteer->fresh()->email_verified_at)->not->toBeNull();
});

it('skips volunteers without a matching verified token', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'unverified@example.com',
        'email_verified_at' => null,
    ]);

    EmailVerificationToken::factory()->create([
        'event_id' => $this->event->id,
        'project_id' => $this->project->id,
        'email' => $volunteer->email,
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => null,
    ]);

    $this->artisan('app:backfill-volunteer-email-verification')
        ->expectsOutput('No volunteers needed backfilling.')
        ->assertSuccessful();

    expect($volunteer->fresh()->email_verified_at)->toBeNull();
});

it('backfills from a verified token on another event in the same project', function () {
    $otherEvent = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $volunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'cross-event-backfill@example.com',
        'email_verified_at' => null,
    ]);

    EmailVerificationToken::factory()->create([
        'event_id' => $otherEvent->id,
        'project_id' => $this->project->id,
        'email' => $volunteer->email,
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subHour(),
    ]);

    $this->artisan('app:backfill-volunteer-email-verification')
        ->expectsOutput('Backfilled 1 volunteer(s).')
        ->assertSuccessful();

    expect($volunteer->fresh()->email_verified_at)->not->toBeNull();
});
