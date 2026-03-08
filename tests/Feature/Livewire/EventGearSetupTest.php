<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventGearSetup;
use App\Models\Event;
use App\Models\EventGearItem;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->event = Event::factory()->for($this->org)->create();
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

    expect(EventGearItem::count())->toBe(1);

    $item = EventGearItem::first();
    expect($item->name)->toBe('T-Shirt')
        ->and($item->requires_size)->toBeTrue()
        ->and($item->available_sizes)->toBe(['S', 'M', 'L', 'XL'])
        ->and($item->event_id)->toBe($this->event->id);
});

it('allows organizer to add non-sized gear item', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->set('newItemName', 'Badge')
        ->call('addItem')
        ->assertHasNoErrors();

    $item = EventGearItem::first();
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
    $item = EventGearItem::factory()->for($this->event)->create();

    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->call('removeItem', $item->id)
        ->assertHasNoErrors();

    expect(EventGearItem::count())->toBe(0);
});

it('denies volunteer admin access to gear setup', function () {
    ['user' => $volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('renders existing gear items', function () {
    EventGearItem::factory()->for($this->event)->create(['name' => 'Vest']);

    Livewire::actingAs($this->organizer)
        ->test(EventGearSetup::class, ['eventId' => $this->event->id])
        ->assertSee('Vest');
});
