<?php

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Volunteer;
use App\Notifications\EmailVerification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Summer Fest',
    ]);
    $this->volunteer = Volunteer::factory()->create(['name' => 'Jane Doe']);
    $this->verificationUrl = 'https://example.com/verify-email/test-token';
});

it('sends email with event details and verification URL', function () {
    Notification::fake();

    $this->volunteer->notify(new EmailVerification($this->event, $this->verificationUrl));

    Notification::assertSentTo($this->volunteer, EmailVerification::class, function ($notification) {
        $mail = $notification->toMail($this->volunteer);

        expect($mail->subject)->toBe('Verify your email for Summer Fest')
            ->and(implode(' ', $mail->introLines))->toContain('Summer Fest')
            ->and(implode(' ', $mail->introLines))->toContain('verify your email');

        return true;
    });
});

it('uses custom template when set', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::EmailVerification,
        'subject' => 'Confirm {{volunteer_name}} for {{event_name}}',
        'body' => 'Custom verification for {{event_name}}',
    ]);

    $notification = new EmailVerification($this->event, $this->verificationUrl);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toBe('Confirm Jane Doe for Summer Fest')
        ->and(implode(' ', $mail->introLines))->toContain('Custom verification');
});

it('uses default template when no custom template exists', function () {
    $notification = new EmailVerification($this->event, $this->verificationUrl);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toBe('Verify your email for Summer Fest')
        ->and(implode(' ', $mail->introLines))->toContain('Summer Fest');
});

it('is queued', function () {
    expect(new EmailVerification($this->event, $this->verificationUrl))
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('includes verification action URL', function () {
    $notification = new EmailVerification($this->event, $this->verificationUrl);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->actionText)->toBe('Verify Email & Complete Signup')
        ->and($mail->actionUrl)->toBe($this->verificationUrl);
});
