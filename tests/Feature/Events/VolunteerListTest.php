<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\VolunteerList;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->published()->create();
});

it('renders for organizer', function () {
    $this->actingAs($this->user)
        ->get(route('events.volunteers', $this->event))
        ->assertOk()
        ->assertSeeLivewire(VolunteerList::class);
});

it('renders for volunteer admin', function () {
    $admin = \App\Models\User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($admin)
        ->get(route('events.volunteers', $this->event))
        ->assertOk();
});

it('denies unauthenticated users', function () {
    $this->get(route('events.volunteers', $this->event))
        ->assertRedirect(route('login'));
});

it('returns 404 for event from different org', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('events.volunteers', $otherEvent))
        ->assertNotFound();
});

it('lists volunteers for the event', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Alice Wonderland']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Alice Wonderland');
});

it('does not show volunteers from other events', function () {
    $otherEvent = Event::factory()->for($this->org)->create();
    $volunteer = Volunteer::factory()->create(['name' => 'Bob Other']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $otherEvent->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertDontSee('Bob Other');
});

it('filters by name', function () {
    $vol1 = Volunteer::factory()->create(['name' => 'Alice Match']);
    $vol2 = Volunteer::factory()->create(['name' => 'Bob Nope']);
    Ticket::factory()->create(['volunteer_id' => $vol1->id, 'event_id' => $this->event->id]);
    Ticket::factory()->create(['volunteer_id' => $vol2->id, 'event_id' => $this->event->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->set('search', 'Alice')
        ->assertSee('Alice Match')
        ->assertDontSee('Bob Nope');
});

it('filters by email', function () {
    $vol = Volunteer::factory()->create(['name' => 'Charlie', 'email' => 'charlie@special.com']);
    Ticket::factory()->create(['volunteer_id' => $vol->id, 'event_id' => $this->event->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->set('search', 'special.com')
        ->assertSee('Charlie');
});

it('shows empty state when no volunteers', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('No volunteers have signed up yet.');
});

it('shows filtered empty state', function () {
    $vol = Volunteer::factory()->create(['name' => 'Alice']);
    Ticket::factory()->create(['volunteer_id' => $vol->id, 'event_id' => $this->event->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->set('search', 'zzzznonexistent')
        ->assertSee('No volunteers match your search.');
});

it('shows arrival badge', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Arrived Alice']);
    $ticket = Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Arrived Alice')
        ->assertSee('Yes');
});

it('shows attendance badge', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Attended Bob']);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $signup = ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    AttendanceRecord::create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $this->user->id,
        'recorded_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Attended Bob')
        ->assertSee('1/1');
});
