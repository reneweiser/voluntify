<?php

use App\Enums\EmailTemplateType;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\Notifications\EventRepublishedNotification;
use App\Services\EmailTemplateRenderer;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create();
});

it('uses event_updated template type', function () {
    $notification = new EventRepublishedNotification($this->event, 'New schedule posted');

    $mail = $notification->toMail($this->volunteer);

    $renderer = app(EmailTemplateRenderer::class);
    $defaults = $renderer->getDefaults(EmailTemplateType::EventUpdated);

    expect($mail->subject)->toContain($this->event->name);
});

it('includes organizer note in email body', function () {
    $notification = new EventRepublishedNotification($this->event, 'Event moved to Saturday');

    $mail = $notification->toMail($this->volunteer);

    $bodyText = collect($mail->introLines)->implode(' ');
    expect($bodyText)->toContain('Event moved to Saturday');
});

it('renders without organizer note', function () {
    $notification = new EventRepublishedNotification($this->event);

    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain($this->event->name);
});

it('greets volunteer by first name', function () {
    $notification = new EventRepublishedNotification($this->event);

    $mail = $notification->toMail($this->volunteer);

    expect($mail->greeting)->toContain($this->volunteer->first_name);
});
