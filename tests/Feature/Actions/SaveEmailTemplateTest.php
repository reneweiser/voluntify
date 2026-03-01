<?php

use App\Actions\DeleteEmailTemplate;
use App\Actions\SaveEmailTemplate;
use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
});

it('creates a new email template', function () {
    $action = new SaveEmailTemplate;

    $template = $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Welcome to {{event_name}}',
        body: 'Hi {{volunteer_name}}, thanks for signing up!',
    );

    expect($template)->toBeInstanceOf(EmailTemplate::class)
        ->and($template->event_id)->toBe($this->event->id)
        ->and($template->type)->toBe(EmailTemplateType::SignupConfirmation)
        ->and($template->subject)->toBe('Welcome to {{event_name}}')
        ->and($template->body)->toBe('Hi {{volunteer_name}}, thanks for signing up!');
});

it('updates existing template on duplicate type', function () {
    $action = new SaveEmailTemplate;

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Original Subject',
        body: 'Original body',
    );

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Updated Subject',
        body: 'Updated body',
    );

    expect(EmailTemplate::where('event_id', $this->event->id)->count())->toBe(1);

    $template = EmailTemplate::where('event_id', $this->event->id)->first();
    expect($template->subject)->toBe('Updated Subject')
        ->and($template->body)->toBe('Updated body');
});

it('allows different types for same event', function () {
    $action = new SaveEmailTemplate;

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Signup Subject',
        body: 'Signup body',
    );

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::PreShiftReminder24h,
        subject: 'Reminder Subject',
        body: 'Reminder body',
    );

    expect(EmailTemplate::where('event_id', $this->event->id)->count())->toBe(2);
});

it('deletes a template and reverts to default', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
    ]);

    $deleteAction = new DeleteEmailTemplate;
    $deleteAction->execute($this->event, EmailTemplateType::SignupConfirmation);

    expect(
        EmailTemplate::where('event_id', $this->event->id)
            ->where('type', EmailTemplateType::SignupConfirmation)
            ->exists()
    )->toBeFalse();
});
