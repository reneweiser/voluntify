<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventGearSetup;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    app()->instance(Organization::class, $this->org);
});

it('allows organizer to add a gear item', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'T-Shirt')
        ->set('newItemRequiresSize', true)
        ->set('newItemSizes', 'S, M, L, XL')
        ->call('addItem')
        ->assertHasNoErrors();

    expect(ProjectGearItem::count())->toBe(1);

    $item = ProjectGearItem::first();
    expect($item->name)->toBe('T-Shirt')
        ->and($item->requires_size)->toBeTrue()
        ->and($item->available_sizes)->toBe(['S', 'M', 'L', 'XL'])
        ->and($item->project_id)->toBe($this->project->id);
});

it('allows organizer to add non-sized gear item', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'Badge')
        ->call('addItem')
        ->assertHasNoErrors();

    $item = ProjectGearItem::first();
    expect($item->requires_size)->toBeFalse()
        ->and($item->available_sizes)->toBeNull();
});

it('validates gear item name is required', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', '')
        ->call('addItem')
        ->assertHasErrors(['newItemName' => 'required']);
});

it('allows organizer to remove a gear item', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create();

    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->call('removeItem', $item->id)
        ->assertHasNoErrors();

    expect(ProjectGearItem::count())->toBe(0);
});

it('denies volunteer admin access to gear setup', function () {
    ['user' => $volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('renders existing gear items', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Vest']);

    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->assertSee('Vest');
});

it('rejects adding sized item when sizes field is empty', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'T-Shirt')
        ->set('newItemRequiresSize', true)
        ->set('newItemSizes', '')
        ->call('addItem')
        ->assertHasErrors(['newItemSizes']);

    expect(ProjectGearItem::count())->toBe(0);
});

it('rejects adding sized item when sizes field is only commas', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'T-Shirt')
        ->set('newItemRequiresSize', true)
        ->set('newItemSizes', ',,,')
        ->call('addItem')
        ->assertHasErrors(['newItemSizes']);

    expect(ProjectGearItem::count())->toBe(0);
});

it('rejects adding sized item when sizes field is only whitespace', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'T-Shirt')
        ->set('newItemRequiresSize', true)
        ->set('newItemSizes', '   ')
        ->call('addItem')
        ->assertHasErrors(['newItemSizes']);

    expect(ProjectGearItem::count())->toBe(0);
});

it('rejects adding sized item when sizes field is commas and whitespace', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'T-Shirt')
        ->set('newItemRequiresSize', true)
        ->set('newItemSizes', ' , , ')
        ->call('addItem')
        ->assertHasErrors(['newItemSizes']);

    expect(ProjectGearItem::count())->toBe(0);
});

it('accepts valid comma-separated sizes', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'T-Shirt')
        ->set('newItemRequiresSize', true)
        ->set('newItemSizes', 'S, M, L, XL')
        ->call('addItem')
        ->assertHasNoErrors();

    $item = ProjectGearItem::first();
    expect($item->available_sizes)->toBe(['S', 'M', 'L', 'XL']);
});

it('accepts non-sized item without sizes field', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'Badge')
        ->set('newItemRequiresSize', false)
        ->set('newItemSizes', '')
        ->call('addItem')
        ->assertHasNoErrors();

    expect(ProjectGearItem::count())->toBe(1);
});
