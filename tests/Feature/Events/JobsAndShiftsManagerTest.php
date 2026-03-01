<?php

use App\Enums\StaffRole;
use App\Livewire\Events\JobsAndShiftsManager;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->create();
});

it('renders the jobs and shifts page', function () {
    $this->actingAs($this->user)
        ->get(route('events.jobs', $this->event))
        ->assertOk()
        ->assertSeeLivewire(JobsAndShiftsManager::class);
});

it('lists jobs with their shifts', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Ticket Scanner']);
    Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 15]);

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->assertSee('Ticket Scanner')
        ->assertSee('15');
});

it('shows empty state when no jobs', function () {
    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->assertSee('No jobs yet');
});

it('allows organizer to create a job', function () {
    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('openCreateJob')
        ->set('jobName', 'Setup Crew')
        ->set('jobDescription', 'Help set up the event')
        ->call('saveJob')
        ->assertHasNoErrors()
        ->assertSee('Setup Crew');

    expect(VolunteerJob::where('name', 'Setup Crew')->exists())->toBeTrue();
});

it('allows organizer to edit a job', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Old Name']);

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('openEditJob', $job->id)
        ->assertSet('jobName', 'Old Name')
        ->set('jobName', 'New Name')
        ->call('saveJob')
        ->assertHasNoErrors();

    expect($job->fresh()->name)->toBe('New Name');
});

it('allows organizer to delete a job', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('deleteJob', $job->id);

    expect(VolunteerJob::find($job->id))->toBeNull();
});

it('prevents deleting job with signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('deleteJob', $job->id)
        ->assertHasErrors('job');

    expect(VolunteerJob::find($job->id))->not->toBeNull();
});

it('allows organizer to create a shift', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('openCreateShift', $job->id)
        ->set('shiftStartsAt', '2026-07-01T09:00')
        ->set('shiftEndsAt', '2026-07-01T13:00')
        ->set('shiftCapacity', 20)
        ->call('saveShift')
        ->assertHasNoErrors();

    expect(Shift::where('volunteer_job_id', $job->id)->count())->toBe(1);
});

it('allows organizer to edit a shift', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('openEditShift', $shift->id)
        ->assertSet('shiftCapacity', 5)
        ->set('shiftCapacity', 25)
        ->call('saveShift')
        ->assertHasNoErrors();

    expect($shift->fresh()->capacity)->toBe(25);
});

it('allows organizer to delete a shift', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->call('deleteShift', $shift->id);

    expect(Shift::find($shift->id))->toBeNull();
});

it('shows signup count per shift', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 10]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->assertSee('1 / 10');
});

it('shows full badge when shift is at capacity', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->user)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->assertSee('Full');
});

it('shows read-only view for volunteer admin', function () {
    $admin = \App\Models\User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Visible Job']);

    Livewire::actingAs($admin)
        ->test(JobsAndShiftsManager::class, ['eventId' => $this->event->id])
        ->assertSee('Visible Job')
        ->assertDontSee('Add Job')
        ->assertDontSee('Add Shift');
});

it('returns 404 for events from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('events.jobs', $otherEvent))
        ->assertNotFound();
});
