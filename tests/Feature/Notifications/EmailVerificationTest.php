<?php

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Volunteer;
use App\Notifications\EmailVerification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Summer Fest',
    ]);
    $this->volunteer = Volunteer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $this->verificationUrl = 'https://example.com/verify-email/test-token';
});

it('sends email with event details and verification URL', function () {
    Notification::fake();

    $this->volunteer->notify(new EmailVerification($this->event, $this->verificationUrl));

    Notification::assertSentTo($this->volunteer, EmailVerification::class, function ($notification) {
        $mail = $notification->toMail($this->volunteer);

        // #81 - Updated to expect German subject and content
        expect($mail->subject)->toBe('Bestätige deine E-Mail für Summer Fest')
            ->and(implode(' ', $mail->introLines))->toContain('Summer Fest')
            ->and(implode(' ', $mail->introLines))->toContain('bestätige');

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

    // #81 - Updated to expect German defaults
    expect($mail->subject)->toBe('Bestätige deine E-Mail für Summer Fest')
        ->and(implode(' ', $mail->introLines))->toContain('Summer Fest');
});

it('is queued', function () {
    expect(new EmailVerification($this->event, $this->verificationUrl))
        ->toBeInstanceOf(ShouldQueue::class);
});

it('includes verification action URL in German', function () {
    $notification = new EmailVerification($this->event, $this->verificationUrl);
    $mail = $notification->toMail($this->volunteer);

    // #81 - Updated to German button text
    expect($mail->actionText)->toBe('E-Mail bestätigen & Anmeldung abschließen')
        ->and($mail->actionUrl)->toBe($this->verificationUrl);
});
