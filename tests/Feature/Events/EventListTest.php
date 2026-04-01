<?php

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\EventList;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
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
        ->call('setStatusFilter', 'published_open')
        ->assertSee('Published Event')
        ->assertDontSee('Draft Event');
});

it('shows empty state when no events', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('No events found');
});

it('shows empty state when user has no organization', function () {
    app()->forgetInstance(Organization::class);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(EventList::class)
        ->assertOk()
        ->assertSee('No events found');
});

it('toggles filter off when clicking same status', function () {
    Event::factory()->for($this->org)->published()->create(['name' => 'Published Event']);
    Event::factory()->for($this->org)->create(['name' => 'Draft Event', 'status' => EventStatus::Draft]);

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->call('setStatusFilter', 'published_open')
        ->assertDontSee('Draft Event')
        ->call('setStatusFilter', 'published_open')
        ->assertSee('Draft Event');
});

it('shows create button for organizers', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->assertSee('Create Event');
});

it('hides create button for project organizers', function () {
    $projectUser = User::factory()->create();
    $project = Project::factory()->for($this->org)->create();
    $project->users()->attach($projectUser, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($projectUser)
        ->test(EventList::class)
        ->assertDontSee('Create Event');
});

it('creates a draft event and redirects to detail', function () {
    $project = Project::factory()->for($this->org)->create();

    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->set('eventProjectId', $project->id)
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
        ->and($event->organization_id)->toBe($this->org->id)
        ->and($event->project_id)->toBe($project->id);
});

it('validates required fields when creating event', function () {
    Livewire::actingAs($this->user)
        ->test(EventList::class)
        ->call('createEvent')
        ->assertHasErrors(['eventProjectId', 'eventName', 'eventStartsAt', 'eventEndsAt']);
});

it('shows volunteer count per event', function () {
    $event = Event::factory()->for($this->org)->published()->create();
    $volunteer = Volunteer::factory()->for($event->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

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

it('project organizer only sees events from assigned project', function () {
    $project1 = Project::factory()->for($this->org)->create();
    $project2 = Project::factory()->for($this->org)->create();

    Event::factory()->for($this->org)->for($project1)->published()->create(['name' => 'Assigned Event']);
    Event::factory()->for($this->org)->for($project2)->published()->create(['name' => 'Other Event']);

    $projectUser = User::factory()->create();
    $project1->users()->attach($projectUser, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($projectUser)
        ->test(EventList::class)
        ->assertSee('Assigned Event')
        ->assertDontSee('Other Event');
});
