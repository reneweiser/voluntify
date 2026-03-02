<?php

use App\Actions\SendEmailVerification;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Volunteer;
use App\Notifications\EmailVerification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->volunteer = Volunteer::factory()->create();
});

it('creates an email verification token record', function () {
    $action = app(SendEmailVerification::class);
    $action->execute($this->volunteer, $this->event, [1, 2, 3]);

    $token = EmailVerificationToken::first();

    expect($token)->not->toBeNull()
        ->and($token->volunteer_id)->toBe($this->volunteer->id)
        ->and($token->event_id)->toBe($this->event->id)
        ->and($token->shift_ids)->toBe([1, 2, 3])
        ->and($token->expires_at->isFuture())->toBeTrue();
});

it('sends email verification notification to volunteer', function () {
    $action = app(SendEmailVerification::class);
    $action->execute($this->volunteer, $this->event, [1]);

    Notification::assertSentTo($this->volunteer, EmailVerification::class);
});

it('hashes the token with SHA-256 and does not store plain token', function () {
    $action = app(SendEmailVerification::class);
    $action->execute($this->volunteer, $this->event, [1]);

    $token = EmailVerificationToken::first();

    // Token hash should be a 64-char hex string (SHA-256)
    expect(strlen($token->token_hash))->toBe(64)
        ->and(ctype_xdigit($token->token_hash))->toBeTrue();
});

it('includes verification URL in the notification', function () {
    $action = app(SendEmailVerification::class);
    $action->execute($this->volunteer, $this->event, [1]);

    Notification::assertSentTo($this->volunteer, EmailVerification::class, function ($notification) {
        return str_contains($notification->verificationUrl, 'verify-email/');
    });
});
