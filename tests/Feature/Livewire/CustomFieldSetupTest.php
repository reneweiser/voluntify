<?php

use App\Enums\StaffRole;
use App\Livewire\Events\CustomFieldSetup;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->published()->create();
});

it('allows organizer to add a text field', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Emergency Contact')
        ->set('newFieldType', 'text')
        ->set('newFieldRequired', true)
        ->call('addField')
        ->assertHasNoErrors();

    expect(CustomRegistrationField::count())->toBe(1);
    $field = CustomRegistrationField::first();
    expect($field->label)->toBe('Emergency Contact')
        ->and($field->type->value)->toBe('text')
        ->and($field->required)->toBeTrue();
});

it('allows organizer to add a select field with choices', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Diet')
        ->set('newFieldType', 'select')
        ->set('newFieldOptions', 'Vegan, Vegetarian, None')
        ->call('addField')
        ->assertHasNoErrors();

    $field = CustomRegistrationField::first();
    expect($field->options['choices'])->toBe(['Vegan', 'Vegetarian', 'None']);
});

it('validates field label is required', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', '')
        ->call('addField')
        ->assertHasErrors(['newFieldLabel' => 'required']);
});

it('validates select field must have choices', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Pick one')
        ->set('newFieldType', 'select')
        ->set('newFieldOptions', '')
        ->call('addField')
        ->assertHasErrors(['newFieldOptions']);
});

it('allows organizer to remove a field via soft delete', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create();

    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->call('removeField', $field->id);

    expect(CustomRegistrationField::find($field->id))->toBeNull()
        ->and(CustomRegistrationField::withTrashed()->find($field->id))->not->toBeNull();
});

it('denies volunteer admin access', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('renders existing fields', function () {
    CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Dietary Needs']);

    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->assertSee('Dietary Needs');
});

it('applies template without saving', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->call('applyTemplate', 'emergency_contact')
        ->assertSet('newFieldLabel', 'Emergency Contact')
        ->assertSet('newFieldType', 'text')
        ->assertSet('newFieldRequired', true);

    expect(CustomRegistrationField::count())->toBe(0);
});

it('shows confirmation when adding required field to event with signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    Ticket::factory()->for($volunteer)->for($this->event->project, 'project')->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Required field')
        ->set('newFieldRequired', true)
        ->call('addField')
        ->assertSet('showSignupWarning', true);

    // Field not saved yet
    expect(CustomRegistrationField::count())->toBe(0);
});

it('creates field and closes warning when confirming required field with signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    Ticket::factory()->for($volunteer)->for($this->event->project, 'project')->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Required field')
        ->set('newFieldRequired', true)
        ->call('addField')
        ->assertSet('showSignupWarning', true)
        ->call('confirmAddField')
        ->assertSet('showSignupWarning', false);

    expect(CustomRegistrationField::count())->toBe(1);
    expect(CustomRegistrationField::first()->label)->toBe('Required field');
});

it('skips warning for non-required field even when event has signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    Ticket::factory()->for($volunteer)->for($this->event->project, 'project')->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Optional notes')
        ->set('newFieldType', 'text')
        ->set('newFieldRequired', false)
        ->call('addField')
        ->assertSet('showSignupWarning', false)
        ->assertHasNoErrors();

    expect(CustomRegistrationField::count())->toBe(1);
});

it('allows organizer to add a checkbox field with choices', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Dietary Needs')
        ->set('newFieldType', 'checkbox')
        ->set('newFieldOptions', 'Opt A, Opt B')
        ->call('addField')
        ->assertHasNoErrors();

    $field = CustomRegistrationField::first();
    expect($field)->not->toBeNull()
        ->and($field->type->value)->toBe('checkbox')
        ->and($field->options['choices'])->toBe(['Opt A', 'Opt B']);
});

it('persists allow_multiple flag on field creation', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->set('newFieldLabel', 'Multi Select')
        ->set('newFieldType', 'select')
        ->set('newFieldOptions', 'A, B, C')
        ->set('newFieldAllowMultiple', true)
        ->call('addField')
        ->assertHasNoErrors();

    $field = CustomRegistrationField::first();
    expect($field->allow_multiple)->toBeTrue();
});

it('applies dietary_restrictions template with allow_multiple', function () {
    Livewire::actingAs($this->user)
        ->test(CustomFieldSetup::class, ['eventId' => $this->event->id])
        ->call('applyTemplate', 'dietary_restrictions')
        ->assertSet('newFieldAllowMultiple', true)
        ->assertSet('newFieldType', 'checkbox')
        ->assertSet('newFieldLabel', 'Dietary Restrictions');
});
