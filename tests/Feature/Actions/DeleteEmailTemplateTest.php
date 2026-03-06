<?php

use App\Actions\DeleteEmailTemplate;
use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
});

it('deletes template by type', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
    ]);

    $action = new DeleteEmailTemplate;
    $action->execute($this->event, EmailTemplateType::SignupConfirmation);

    expect(
        $this->event->emailTemplates()->where('type', EmailTemplateType::SignupConfirmation)->count()
    )->toBe(0);
});

it('does not affect other template types', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
    ]);
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::PreShiftReminder24h,
    ]);

    $action = new DeleteEmailTemplate;
    $action->execute($this->event, EmailTemplateType::SignupConfirmation);

    expect($this->event->emailTemplates()->count())->toBe(1)
        ->and($this->event->emailTemplates()->first()->type)->toBe(EmailTemplateType::PreShiftReminder24h);
});

it('is a no-op when template does not exist', function () {
    $action = new DeleteEmailTemplate;
    $action->execute($this->event, EmailTemplateType::SignupConfirmation);

    expect($this->event->emailTemplates()->count())->toBe(0);
});
