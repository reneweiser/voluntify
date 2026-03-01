<?php

use App\Enums\EventStatus;
use App\Livewire\Events\EventList;
use App\Models\Event;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
});

it('renders the events page', function () {
    $this->actingAs($this->user)
        ->get(route('events.index'))
        ->assertOk()
        ->assertSeeLivewire(EventList::class);
});

it('lists events for the organization', function () {
    $event = Event::factory()->for($this->org)->published()->create(['name' => 'Community Fair']);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('Community Fair');
});

it('does not show events from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    Event::factory()->for($otherOrg)->create(['name' => 'Other Org Event']);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertDontSee('Other Org Event');
});

it('filters events by status', function () {
    Event::factory()->for($this->org)->published()->create(['name' => 'Published Event']);
    Event::factory()->for($this->org)->create(['name' => 'Draft Event', 'status' => EventStatus::Draft]);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('Published Event')
        ->assertSee('Draft Event')
        ->call('setStatusFilter', 'published')
        ->assertSee('Published Event')
        ->assertDontSee('Draft Event');
});

it('shows empty state when no events', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('No events found');
});

it('toggles filter off when clicking same status', function () {
    Event::factory()->for($this->org)->published()->create(['name' => 'Published Event']);
    Event::factory()->for($this->org)->create(['name' => 'Draft Event', 'status' => EventStatus::Draft]);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->call('setStatusFilter', 'published')
        ->assertDontSee('Draft Event')
        ->call('setStatusFilter', 'published')
        ->assertSee('Draft Event');
});
