<?php

use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Livewire\Scanner\ManualLookup;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();

    app()->instance(Organization::class, $this->org);
});

// Phase D: Basics

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.lookup', $this->event))
        ->assertOk()
        ->assertSeeLivewire(ManualLookup::class);
});

it('renders for project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.lookup', $this->event))
        ->assertOk();
});

it('shows event name', function () {
    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->assertSee($this->event->name);
});

it('shows empty search state', function () {
    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->assertSee('Search for a volunteer');
});

// Phase E: Server Search

it('finds volunteers by name', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Johnson']);
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->set('search', 'Alice')
        ->assertSee('Alice Johnson');
});

it('scopes search to selected event', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $volunteer = Volunteer::factory()->for($otherProject)->create(['first_name' => 'Bob', 'last_name' => 'Smith']);
    $otherEvent = Event::factory()->for($this->org)->for($otherProject)->published()->create();
    $job = VolunteerJob::factory()->for($otherEvent)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->set('search', 'Bob')
        ->assertDontSee('Bob Smith');
});

it('shows no results state', function () {
    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->set('search', 'Nonexistent Person')
        ->assertSee('No volunteers found');
});

it('shows job and shift info', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Carol', 'last_name' => 'Davis']);
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Gate Watch']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->set('search', 'Carol')
        ->assertSee('Carol Davis')
        ->assertSee('Gate Watch');
});

it('shows already arrived for checked-in volunteer', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Dave', 'last_name' => 'Wilson']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    EventArrival::factory()->create([
        'ticket_id' => $ticket->id,
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'scanned_by' => $this->organizer->id,
        'method' => ArrivalMethod::QrScan,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->set('search', 'Dave')
        ->assertSee('Dave Wilson')
        ->assertSee('Already arrived');
});

// Phase F: Confirm Arrival

it('records arrival on confirm', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Eve', 'last_name' => 'Brown']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->call('confirmArrival', $volunteer->id);

    $this->assertDatabaseHas('event_arrivals', [
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'scanned_by' => $this->organizer->id,
    ]);
});

it('sets method to manual_lookup', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Frank', 'last_name' => 'Green']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->call('confirmArrival', $volunteer->id);

    $this->assertDatabaseHas('event_arrivals', [
        'volunteer_id' => $volunteer->id,
        'method' => ArrivalMethod::ManualLookup->value,
    ]);
});

it('flags duplicate arrival', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Grace', 'last_name' => 'Lee']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
    EventArrival::factory()->create([
        'ticket_id' => $ticket->id,
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'scanned_by' => $this->organizer->id,
        'method' => ArrivalMethod::QrScan,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->call('confirmArrival', $volunteer->id);

    $this->assertDatabaseHas('event_arrivals', [
        'volunteer_id' => $volunteer->id,
        'method' => ArrivalMethod::ManualLookup->value,
        'flagged' => true,
    ]);
});

it('dispatches arrival-confirmed event on success', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Hank', 'last_name' => 'Miller']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->call('confirmArrival', $volunteer->id)
        ->assertDispatched('arrival-confirmed');
});

it('cannot confirm volunteer from wrong event', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherEvent = Event::factory()->for($this->org)->for($otherProject)->published()->create();
    $volunteer = Volunteer::factory()->for($otherProject)->create(['first_name' => 'Jack', 'last_name' => 'White']);
    Ticket::factory()->for($volunteer)->for($otherProject, 'project')->create();

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(ManualLookup::class, ['eventId' => $this->event->id])
        ->call('confirmArrival', $volunteer->id);
});
