<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\VolunteerList;
use App\Models\AttendanceRecord;
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
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
});

it('renders for organizer', function () {
    $this->actingAs($this->user)
        ->get(route('events.volunteers', $this->event))
        ->assertOk()
        ->assertSeeLivewire(VolunteerList::class);
});

it('renders for project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
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
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Wonderland']);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Alice Wonderland');
});

it('does not show volunteers from other events', function () {
    $otherEvent = Event::factory()->for($this->org)->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->for($otherEvent->project)->create(['first_name' => 'Bob', 'last_name' => 'Other']);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $otherShift->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertDontSee('Bob Other');
});

it('filters by name', function () {
    $vol1 = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Match']);
    $vol2 = Volunteer::factory()->for($this->project)->create(['first_name' => 'Bob', 'last_name' => 'Nope']);
    ShiftSignup::factory()->create(['volunteer_id' => $vol1->id, 'shift_id' => $this->shift->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $vol2->id, 'shift_id' => $this->shift->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->set('search', 'Alice')
        ->assertSee('Alice Match')
        ->assertDontSee('Bob Nope');
});

it('filters by email', function () {
    $vol = Volunteer::factory()->for($this->project)->create(['first_name' => 'Charlie', 'last_name' => 'Test', 'email' => 'charlie@special.com']);
    ShiftSignup::factory()->create(['volunteer_id' => $vol->id, 'shift_id' => $this->shift->id]);

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
    $vol = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Test']);
    ShiftSignup::factory()->create(['volunteer_id' => $vol->id, 'shift_id' => $this->shift->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->set('search', 'zzzznonexistent')
        ->assertSee('No volunteers match your search.');
});

it('shows arrival badge', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Arrived', 'last_name' => 'Alice']);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);
    $ticket = Ticket::factory()->for($volunteer)->for($this->project, 'project')->create();
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

it('shows export csv button', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Export CSV');
});

it('export route returns csv', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Export', 'last_name' => 'Test']);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);

    $response = $this->actingAs($this->user)
        ->get(route('events.volunteers.export', $this->event))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('Export')
        ->toContain('"First Name","Last Name",Email,Phone,Shifts,Arrived,Attendance');
});

it('shows attendance badge', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Attended', 'last_name' => 'Bob']);
    $signup = ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);
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

it('shows cancelled signup count separately from active shifts', function () {
    $secondShift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $thirdShift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Cancelled', 'last_name' => 'Count']);

    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $secondShift->id]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $thirdShift->id,
        'cancelled_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Cancelled Count')
        ->assertSeeHtml('data-test="volunteer-active-shifts-'.$volunteer->id.'">2</span>')
        ->assertSee('1 storniert');
});

it('excludes cancelled signups from attendance totals', function () {
    $secondShift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Attendance', 'last_name' => 'Filtered']);

    $activeSignup = ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $secondShift->id,
        'cancelled_at' => now(),
    ]);

    AttendanceRecord::create([
        'shift_signup_id' => $activeSignup->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $this->user->id,
        'recorded_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Attendance Filtered')
        ->assertSeeHtml('data-test="volunteer-attendance-'.$volunteer->id.'">')
        ->assertSee('1/1')
        ->assertDontSee('1/2')
        ->assertSee('1 storniert');
});

it('keeps volunteers with only cancelled signups visible', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Only', 'last_name' => 'Cancelled']);

    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerList::class, ['eventId' => $this->event->id])
        ->assertSee('Only Cancelled')
        ->assertSeeHtml('data-test="volunteer-active-shifts-'.$volunteer->id.'">0</span>')
        ->assertSee('1 storniert')
        ->assertSee('None');
});
