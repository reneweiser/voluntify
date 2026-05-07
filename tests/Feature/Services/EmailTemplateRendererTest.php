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
    // #81 - Updated to expect German defaults with vorname placeholder
    $variables = array_merge($this->variables, [
        'vorname' => 'Jane',
        'nachname' => 'Doe',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        $variables,
    );

    expect($rendered['subject'])->toBe('Anmeldebestätigung für Summer Fest')
        ->and($rendered['body'])->toContain('Hallo Jane')
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

// ============================================================================
// #81 German Defaults & New Template Types
// ============================================================================

it('has German default for signup confirmation [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::SignupConfirmation);

    expect($defaults['subject'])->toContain('Anmeldebestätigung')
        ->and($defaults['body'])->toContain('Hallo {{vorname}}');
});

it('has German default for pre-shift reminder 24h [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::PreShiftReminder24h);

    expect($defaults['subject'])->toContain('Erinnerung')
        ->and($defaults['subject'])->toContain('{{relativer_tag}}')
        ->and($defaults['body'])->toContain('Hallo {{vorname}}')
        ->and($defaults['body'])->toContain('{{relativer_tag}} stattfindet')
        ->and($defaults['body'])->toContain('{{portal_link}}')
        ->and($defaults['body'])->toContain('Bis bald!');
});

it('has German default for pre-shift reminder 4h [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::PreShiftReminder4h);

    expect($defaults['subject'])->toContain('Erinnerung')
        ->and($defaults['subject'])->toContain('bald')
        ->and($defaults['body'])->toContain('Hallo {{vorname}}')
        ->and($defaults['body'])->toContain('{{relativer_tag}} in wenigen Stunden')
        ->and($defaults['body'])->toContain('{{portal_link}}');
});

it('does not inject portal_link into custom reminder bodies unless requested', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::PreShiftReminder24h,
        'subject' => 'Custom {{event_name}}',
        'body' => 'Nur {{job_name}}',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::PreShiftReminder24h,
        $this->event,
        array_merge($this->variables, [
            'portal_link' => 'https://example.com/portal/reminder',
        ]),
    );

    expect($rendered['body'])->toBe('Nur Setup Crew');
});

it('has German default for email verification [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::EmailVerification);

    expect($defaults['subject'])->toContain('Bestätige')
        ->and($defaults['body'])->toContain('Hallo {{vorname}}');
});

it('has German default for staff invitation [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::StaffInvitation);

    expect($defaults['subject'])->toContain('Einladung')
        ->and($defaults['body'])->toContain('Hallo {{name}}');
});

it('has German default for volunteer promoted [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::VolunteerPromoted);

    expect($defaults['subject'])->toContain('befördert')
        ->and($defaults['body'])->toContain('Hallo {{name}}');
});

it('has German default for added to organization [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::AddedToOrganization);

    expect($defaults['subject'])->toContain('hinzugefügt')
        ->and($defaults['body'])->toContain('Hallo {{name}}');
});

it('has German default for event announcement [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::EventAnnouncement);

    expect($defaults)->toHaveKeys(['subject', 'body']);
});

it('has German default for event updated [#81]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::EventUpdated);

    expect($defaults['subject'])->toContain('Aktualisierung')
        ->and($defaults['body'])->toContain('{{organizer_note}}');
});

it('has defaults for all 10 template types [#81]', function () {
    foreach (EmailTemplateType::cases() as $type) {
        $defaults = $this->renderer->getDefaults($type);
        expect($defaults)->toHaveKeys(['subject', 'body']);
    }
});

it('renders vorname placeholder in German default [#81]', function () {
    $variables = [
        'vorname' => 'Anna',
        'nachname' => 'Schmidt',
        'event_name' => 'Summer Fest',
        'shifts_summary' => '- Setup: 10:00',
        'event_location' => 'Berlin',
        'portal_link' => 'https://example.com/portal',
    ];

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        $variables,
    );

    expect($rendered['body'])->toContain('Hallo Anna');
});

it('renders portal_link placeholder [#81]', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => 'Test',
        'body' => 'Portal: {{portal_link}}',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        ['portal_link' => 'https://example.com/portal/abc'],
    );

    expect($rendered['body'])->toContain('https://example.com/portal/abc');
});

it('renders kontakt_email placeholder [#81]', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => 'Test',
        'body' => 'Kontakt: {{kontakt_email}}',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        ['kontakt_email' => 'kontakt@example.com'],
    );

    expect($rendered['body'])->toContain('kontakt@example.com');
});

it('renders project_name placeholder [#81]', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => 'Test',
        'body' => 'Projekt: {{project_name}}',
    ]);

    $rendered = $this->renderer->render(
        EmailTemplateType::SignupConfirmation,
        $this->event,
        ['project_name' => 'Sommerfest 2026'],
    );

    expect($rendered['body'])->toContain('Sommerfest 2026');
});

it('renders organizer_note placeholder for event updated [#81]', function () {
    $rendered = $this->renderer->render(
        EmailTemplateType::EventUpdated,
        $this->event,
        [
            'vorname' => 'Anna',
            'event_name' => 'Summer Fest',
            'organizer_note' => 'Die Startzeit hat sich geändert.',
            'portal_link' => 'https://example.com/portal',
        ],
    );

    expect($rendered['body'])->toContain('Die Startzeit hat sich geändert.');
});

it('returns new placeholders for signup confirmation [#81]', function () {
    $placeholders = $this->renderer->availablePlaceholders(EmailTemplateType::SignupConfirmation);

    expect($placeholders)->toContain('vorname')
        ->and($placeholders)->toContain('nachname')
        ->and($placeholders)->toContain('telefon')
        ->and($placeholders)->toContain('portal_link')
        ->and($placeholders)->toContain('kontakt_email')
        ->and($placeholders)->toContain('project_name');
});

it('returns placeholders for event updated [#81]', function () {
    $placeholders = $this->renderer->availablePlaceholders(EmailTemplateType::EventUpdated);

    expect($placeholders)->toContain('vorname')
        ->and($placeholders)->toContain('event_name')
        ->and($placeholders)->toContain('organizer_note')
        ->and($placeholders)->toContain('portal_link');
});

it('returns relativer_tag for pre-shift reminder placeholders', function (EmailTemplateType $type) {
    $placeholders = $this->renderer->availablePlaceholders($type);

    expect($placeholders)->toContain('relativer_tag');
})->with([
    EmailTemplateType::PreShiftReminder24h,
    EmailTemplateType::PreShiftReminder4h,
]);

// ============================================================================
// #104 Cancellation Confirmation
// ============================================================================

it('has German default for cancellation confirmation [#104]', function () {
    $defaults = $this->renderer->getDefaults(EmailTemplateType::CancellationConfirmation);

    expect($defaults['subject'])->toContain('Stornierungsbestätigung')
        ->and($defaults['body'])->toContain('Hallo {{vorname}}')
        ->and($defaults['body'])->toContain('{{cancelled_shift_summary}}')
        ->and($defaults['body'])->toContain('{{remaining_shifts_section}}');
});

it('returns placeholders for cancellation confirmation [#104]', function () {
    $placeholders = $this->renderer->availablePlaceholders(EmailTemplateType::CancellationConfirmation);

    expect($placeholders)->toContain('vorname')
        ->and($placeholders)->toContain('nachname')
        ->and($placeholders)->toContain('event_name')
        ->and($placeholders)->toContain('cancelled_shift_summary')
        ->and($placeholders)->toContain('remaining_shifts_section')
        ->and($placeholders)->toContain('portal_link')
        ->and($placeholders)->toContain('kontakt_email')
        ->and($placeholders)->toContain('project_name');
});

it('renders cancellation confirmation template with variables [#104]', function () {
    $rendered = $this->renderer->render(
        EmailTemplateType::CancellationConfirmation,
        $this->event,
        [
            'vorname' => 'Anna',
            'nachname' => 'Schmidt',
            'event_name' => 'Summer Fest',
            'cancelled_shift_summary' => '- Einlass: 01.07.2026 10:00 — 14:00',
            'remaining_shifts_section' => "**Deine verbleibenden Schichten:**\n- Aufbau: 01.07.2026 08:00 — 10:00",
            'portal_link' => 'https://example.com/my-ticket/abc123',
            'kontakt_email' => 'kontakt@example.com',
            'project_name' => 'Sommerfest 2026',
        ],
    );

    expect($rendered['subject'])->toBe('Stornierungsbestätigung für Summer Fest')
        ->and($rendered['body'])->toContain('Hallo Anna')
        ->and($rendered['body'])->toContain('Einlass: 01.07.2026 10:00 — 14:00')
        ->and($rendered['body'])->toContain('verbleibenden Schichten')
        ->and($rendered['body'])->toContain('https://example.com/my-ticket/abc123');
});
