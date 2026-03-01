<?php

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\EventList;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;
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

it('redirects to dashboard when user has no organization', function () {
    app()->forgetInstance(Organization::class);
    $user = \App\Models\User::factory()->create();

    Livewire::actingAs($user)
        ->test(EventList::class)
        ->assertRedirect(route('dashboard'));
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

it('shows create button for organizers', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('Create Event');
});

it('hides create button for volunteer admins', function () {
    $admin = \App\Models\User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(EventList::class)
        ->assertDontSee('Create Event');
});

it('creates a draft event and redirects to detail', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->set('eventName', 'New Festival')
        ->set('eventDescription', 'A fun event')
        ->set('eventLocation', 'The Park')
        ->set('eventStartsAt', '2026-08-01T10:00')
        ->set('eventEndsAt', '2026-08-01T18:00')
        ->call('createEvent')
        ->assertHasNoErrors();

    $event = Event::where('name', 'New Festival')->first();

    expect($event)->not->toBeNull()
        ->and($event->status)->toBe(EventStatus::Draft)
        ->and($event->organization_id)->toBe($this->org->id);
});

it('validates required fields when creating event', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->call('createEvent')
        ->assertHasErrors(['eventName', 'eventStartsAt', 'eventEndsAt']);
});

it('shows volunteer count per event', function () {
    $event = Event::factory()->for($this->org)->published()->create();
    $volunteer = Volunteer::factory()->create();
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $event->id]);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('1 volunteers');
});

it('makes event rows clickable links to detail page', function () {
    $event = Event::factory()->for($this->org)->create(['name' => 'Clickable Event']);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSeeHtml(route('events.show', $event));
});
