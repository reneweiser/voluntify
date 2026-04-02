<?php

use App\Actions\DeleteEmailTemplate;
use App\Actions\SaveEmailTemplate;
use App\Enums\EmailTemplateType;
use App\Events\Activity\EmailTemplateUpdated;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
});

it('creates a new email template', function () {
    $action = new SaveEmailTemplate;

    $template = $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Welcome to {{event_name}}',
        body: 'Hi {{volunteer_name}}, thanks for signing up!',
        causer: $this->user,
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
        causer: $this->user,
    );

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Updated Subject',
        body: 'Updated body',
        causer: $this->user,
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
        causer: $this->user,
    );

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::PreShiftReminder24h,
        subject: 'Reminder Subject',
        body: 'Reminder body',
        causer: $this->user,
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

it('dispatches EmailTemplateUpdated activity event with causer', function () {
    EventFacade::fake([EmailTemplateUpdated::class]);

    $action = new SaveEmailTemplate;

    $action->execute(
        event: $this->event,
        type: EmailTemplateType::SignupConfirmation,
        subject: 'Test Subject',
        body: 'Test body',
        causer: $this->user,
    );

    EventFacade::assertDispatched(EmailTemplateUpdated::class, fn ($e) => $e->causer->id === $this->user->id);
});
