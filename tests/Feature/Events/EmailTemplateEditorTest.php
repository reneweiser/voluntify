<?php

use App\Enums\EmailTemplateType;
use App\Enums\StaffRole;
use App\Livewire\Events\EmailTemplateEditor;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Test Event',
        'location' => 'Test Location',
    ]);
});

it('renders the email template editor', function () {
    $this->actingAs($this->user)
        ->get(route('events.emails', $this->event))
        ->assertOk()
        ->assertSeeLivewire(EmailTemplateEditor::class);
});

it('loads default template on mount', function () {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->assertSet('selectedType', EmailTemplateType::SignupConfirmation->value)
        ->assertNotSet('subject', '');
});

it('saves a custom template', function () {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('subject', 'Custom Subject for {{event_name}}')
        ->set('body', 'Custom body for {{volunteer_name}}')
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('template-saved');

    expect(EmailTemplate::where('event_id', $this->event->id)->count())->toBe(1);

    $template = EmailTemplate::where('event_id', $this->event->id)->first();
    expect($template->subject)->toBe('Custom Subject for {{event_name}}')
        ->and($template->body)->toBe('Custom body for {{volunteer_name}}');
});

it('resets template to default', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => 'Custom',
        'body' => 'Custom body',
    ]);

    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->call('resetToDefault')
        ->assertDispatched('template-reset');

    expect(
        EmailTemplate::where('event_id', $this->event->id)
            ->where('type', EmailTemplateType::SignupConfirmation)
            ->exists()
    )->toBeFalse();
});

it('switches between template types', function () {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', EmailTemplateType::PreShiftReminder24h->value)
        ->assertNotSet('subject', '');
});

it('shows preview with sample data', function () {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->call('previewTemplate')
        ->assertSet('showPreview', true)
        ->assertNotSet('previewSubject', '')
        ->assertNotSet('previewBody', '');
});

it('denies access to volunteer admin', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('validates subject and body are required', function () {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('subject', '')
        ->set('body', '')
        ->call('saveTemplate')
        ->assertHasErrors(['subject', 'body']);
});

it('returns 404 for events from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('events.emails', $otherEvent))
        ->assertNotFound();
});

// ============================================================================
// #55 Event-Level Email Templates — All 9 Types
// ============================================================================

it('can save custom templates for all 9 types [#55]', function (EmailTemplateType $type) {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', $type->value)
        ->set('subject', "Custom subject for {$type->value}")
        ->set('body', "Custom body for {$type->value}")
        ->call('saveTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('template-saved');

    expect(EmailTemplate::where('event_id', $this->event->id)
        ->where('type', $type)
        ->exists()
    )->toBeTrue();
})->with(fn () => EmailTemplateType::cases());

it('loads correct defaults for all 9 types [#55]', function (EmailTemplateType $type) {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', $type->value)
        ->assertNotSet('subject', '')
        ->assertNotSet('body', '');
})->with(fn () => EmailTemplateType::cases());

it('shows correct available placeholders for new types [#55]', function () {
    $component = Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', EmailTemplateType::EventUpdated->value);

    $placeholders = $component->instance()->availablePlaceholders();

    expect($placeholders)->toContain('vorname')
        ->and($placeholders)->toContain('organizer_note')
        ->and($placeholders)->toContain('portal_link');
});

it('shows relativer_tag in pre-shift reminder placeholders', function (EmailTemplateType $type) {
    $component = Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', $type->value);

    expect($component->instance()->availablePlaceholders())->toContain('relativer_tag');
})->with([
    EmailTemplateType::PreShiftReminder24h,
    EmailTemplateType::PreShiftReminder4h,
]);

it('preview renders with German sample data for new types [#55]', function () {
    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', EmailTemplateType::EventUpdated->value)
        ->call('previewTemplate')
        ->assertSet('showPreview', true)
        ->assertNotSet('previewSubject', '')
        ->assertNotSet('previewBody', '');
});

it('custom template overrides system default in rendered email [#55]', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::EventUpdated,
        'subject' => 'Custom: Update zu {{event_name}}',
        'body' => 'Eigener Text: {{organizer_note}}',
    ]);

    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', EmailTemplateType::EventUpdated->value)
        ->assertSet('subject', 'Custom: Update zu {{event_name}}')
        ->assertSet('body', 'Eigener Text: {{organizer_note}}');
});

it('reset to default works for new types [#55]', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::EventUpdated,
        'subject' => 'Custom',
        'body' => 'Custom body',
    ]);

    Livewire::actingAs($this->user)
        ->test(EmailTemplateEditor::class, ['eventId' => $this->event->id])
        ->set('selectedType', EmailTemplateType::EventUpdated->value)
        ->call('resetToDefault')
        ->assertDispatched('template-reset');

    expect(
        EmailTemplate::where('event_id', $this->event->id)
            ->where('type', EmailTemplateType::EventUpdated)
            ->exists()
    )->toBeFalse();
});
