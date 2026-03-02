<?php

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Services\EmailTemplateRenderer;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Summer Fest',
        'location' => 'Central Park',
    ]);
    $this->renderer = new EmailTemplateRenderer;
    $this->variables = [
        'volunteer_name' => 'Jane Doe',
        'event_name' => 'Summer Fest',
        'shifts_summary' => '- Setup Crew: Jul 01, 2026 10:00 AM — 2:00 PM',
        'job_name' => 'Setup Crew',
        'shift_date' => 'Jul 01, 2026',
        'shift_time' => '10:00 AM — 2:00 PM',
        'event_location' => '**Location:** Central Park',
    ];
});

it('renders default template when no custom template exists', function () {
    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        $this->variables,
    );

    expect($rendered['subject'])->toBe("You're signed up for Summer Fest!")
        ->and($rendered['body'])->toContain('Jane Doe')
        ->and($rendered['body'])->toContain('Summer Fest')
        ->and($rendered['body'])->toContain('Setup Crew: Jul 01, 2026 10:00 AM');
});

it('renders custom template when one exists', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => 'Hey {{volunteer_name}}, welcome!',
        'body' => 'You signed up for {{event_name}} as {{job_name}}.',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        $this->variables,
    );

    expect($rendered['subject'])->toBe('Hey Jane Doe, welcome!')
        ->and($rendered['body'])->toBe('You signed up for Summer Fest as Setup Crew.');
});

it('replaces all placeholders correctly', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => '{{event_name}}: {{volunteer_name}}',
        'body' => '{{job_name}} on {{shift_date}} at {{shift_time}} {{event_location}}',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        $this->variables,
    );

    expect($rendered['subject'])->toBe('Summer Fest: Jane Doe')
        ->and($rendered['body'])->toContain('Setup Crew')
        ->and($rendered['body'])->toContain('Jul 01, 2026')
        ->and($rendered['body'])->toContain('10:00 AM — 2:00 PM')
        ->and($rendered['body'])->toContain('Central Park');
});

it('leaves unknown placeholders as-is', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => '{{event_name}} {{unknown_var}}',
        'body' => 'Body with {{another_unknown}}',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        $this->variables,
    );

    expect($rendered['subject'])->toBe('Summer Fest {{unknown_var}}')
        ->and($rendered['body'])->toContain('{{another_unknown}}');
});

it('returns available placeholders for template types', function () {
    $placeholders = $this->renderer->availablePlaceholders(EmailTemplateType::SignupConfirmation);

    expect($placeholders)->toContain('volunteer_name')
        ->and($placeholders)->toContain('event_name')
        ->and($placeholders)->toContain('shifts_summary')
        ->and($placeholders)->toContain('job_name')
        ->and($placeholders)->toContain('shift_date')
        ->and($placeholders)->toContain('shift_time')
        ->and($placeholders)->toContain('event_location');
});

it('returns default templates', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::SignupConfirmation);

    expect($defaults)->toHaveKeys(['subject', 'body'])
        ->and($defaults['subject'])->toContain('{{event_name}}');
});
