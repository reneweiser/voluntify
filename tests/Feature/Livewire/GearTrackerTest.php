<?php

use App\Enums\StaffRole;
use App\Livewire\Events\GearTracker;
use App\Models\Event;
use App\Models\EventGearItem;
use App\Models\Organization;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->event = Event::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('marks gear as picked up via toggle', function () {
    $item = EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);
    $volunteer = Volunteer::factory()->verified()->create();
    $gear = VolunteerGear::factory()->create([
        'event_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('togglePickup', $gear->id)
        ->assertHasNoErrors();

    $gear->refresh();
    expect($gear->picked_up_at)->not->toBeNull()
        ->and($gear->picked_up_by)->toBe($this->organizer->id);
});

it('creates gear on-demand when marking pickup for volunteer without record', function () {
    $item = EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);
    $volunteer = Volunteer::factory()->verified()->create();

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('assignAndPickup', $item->id, $volunteer->id)
        ->assertHasNoErrors();

    $gear = VolunteerGear::where('event_gear_item_id', $item->id)
        ->where('volunteer_id', $volunteer->id)
        ->first();

    expect($gear)->not->toBeNull()
        ->and($gear->picked_up_at)->not->toBeNull();
});

it('denies entrance staff access to gear tracker', function () {
    $entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($entranceStaff, ['role' => StaffRole::EntranceStaff]);

    Livewire::actingAs($entranceStaff)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('renders volunteers with gear status', function () {
    $item = EventGearItem::factory()->for($this->event)->create(['name' => 'Lanyard']);
    $volunteer = Volunteer::factory()->verified()->create(['name' => 'Jane Doe']);
    VolunteerGear::factory()->create([
        'event_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    // Need to make volunteer associated with this event via a ticket
    \App\Models\Ticket::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->assertSee('Jane Doe')
        ->assertSee('Lanyard');
});
