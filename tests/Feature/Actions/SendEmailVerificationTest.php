<?php

use App\Actions\SendEmailVerification;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\Notifications\EmailVerification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
});

it('creates token with email and no volunteer_id for new volunteers', function () {
    $action = app(SendEmailVerification::class);
    $token = $action->execute('new@example.com', $this->event);

    expect($token)->toBeInstanceOf(EmailVerificationToken::class)
        ->and($token->email)->toBe('new@example.com')
        ->and($token->volunteer_id)->toBeNull()
        ->and($token->event_id)->toBe($this->event->id)
        ->and($token->project_id)->toBe($this->event->project_id);
});

it('creates token with volunteer_id for existing volunteers', function () {
    $action = app(SendEmailVerification::class);
    $token = $action->execute($this->volunteer->email, $this->event, $this->volunteer);

    expect($token->volunteer_id)->toBe($this->volunteer->id)
        ->and($token->email)->toBe($this->volunteer->email);
});

it('creates token with null shift_ids', function () {
    $action = app(SendEmailVerification::class);
    $token = $action->execute('test@example.com', $this->event);

    expect($token->shift_ids)->toBeNull();
});

it('returns the created token model', function () {
    $action = app(SendEmailVerification::class);
    $result = $action->execute('test@example.com', $this->event);

    expect($result)->toBeInstanceOf(EmailVerificationToken::class)
        ->and($result->exists)->toBeTrue();
});

it('sends notification to bare email when no volunteer', function () {
    $action = app(SendEmailVerification::class);
    $action->execute('anonymous@example.com', $this->event);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        EmailVerification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'anonymous@example.com';
        },
    );
});

it('sends notification via volunteer model when volunteer exists', function () {
    $action = app(SendEmailVerification::class);
    $action->execute($this->volunteer->email, $this->event, $this->volunteer);

    Notification::assertSentTo($this->volunteer, EmailVerification::class);
});

it('hashes the token with SHA-256 and does not store plain token', function () {
    $action = app(SendEmailVerification::class);
    $token = $action->execute('test@example.com', $this->event);

    expect(strlen($token->token_hash))->toBe(64)
        ->and(ctype_xdigit($token->token_hash))->toBeTrue();
});

it('includes verification URL in the notification', function () {
    $action = app(SendEmailVerification::class);
    $action->execute($this->volunteer->email, $this->event, $this->volunteer);

    Notification::assertSentTo($this->volunteer, EmailVerification::class, function ($notification) {
        return str_contains($notification->verificationUrl, 'verify-email/');
    });
});

it('stores project_id on the verification token from event', function () {
    $action = app(SendEmailVerification::class);
    $token = $action->execute('test@example.com', $this->event);

    expect($token->project_id)->toBe($this->event->project_id)
        ->and($token->project_id)->not->toBeNull();
});
