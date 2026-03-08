<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\AttendanceTracker;
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
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Stage Crew']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
});

it('renders for organizer', function () {
    $this->actingAs($this->user)
        ->get(route('events.attendance', $this->event))
        ->assertOk()
        ->assertSeeLivewire(AttendanceTracker::class);
});

it('renders for volunteer admin', function () {
    $volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->get(route('events.attendance', $this->event))
        ->assertOk();
});

it('denies entrance staff', function () {
    $entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->actingAs($entranceStaff)
        ->get(route('events.attendance', $this->event))
        ->assertForbidden();
});

it('lists shifts grouped by job', function () {
    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->assertSee('Stage Crew');
});

it('shows signups when shift is selected', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Alice Smith']);
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $this->shift->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->set('selectedShiftId', $this->shift->id)
        ->assertSee('Alice Smith');
});

it('marks attendance as on time', function () {
    $volunteer = Volunteer::factory()->create();
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $this->shift->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->set('selectedShiftId', $this->shift->id)
        ->call('markStatus', $signup->id, 'on_time')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendance_records', [
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime->value,
        'recorded_by' => $this->user->id,
    ]);
});

it('shows conflict warning when marking no show with arrival', function () {
    $volunteer = Volunteer::factory()->create();
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $this->shift->id,
    ]);
    $ticket = Ticket::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
    ]);
    EventArrival::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->set('selectedShiftId', $this->shift->id)
        ->call('markStatus', $signup->id, 'no_show')
        ->assertSee('Warning');
});

it('prevents marking attendance on a different event shift', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create();
    $signup = ShiftSignup::factory()->create(['shift_id' => $otherShift->id]);

    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->set('selectedShiftId', $this->shift->id)
        ->call('markStatus', $signup->id, 'on_time');
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('excludes cancelled signups from attendance view', function () {
    $active = Volunteer::factory()->create(['name' => 'Active Volunteer']);
    ShiftSignup::factory()->create([
        'volunteer_id' => $active->id,
        'shift_id' => $this->shift->id,
    ]);

    $cancelled = Volunteer::factory()->create(['name' => 'Cancelled Volunteer']);
    ShiftSignup::factory()->create([
        'volunteer_id' => $cancelled->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->set('selectedShiftId', $this->shift->id)
        ->assertSee('Active Volunteer')
        ->assertDontSee('Cancelled Volunteer');
});

it('shows empty state when no shift selected', function () {
    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->assertSee('Select a shift');
});

it('updates attendance count after marking', function () {
    $volunteer = Volunteer::factory()->create();
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $this->shift->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(AttendanceTracker::class, ['eventId' => $this->event->id])
        ->set('selectedShiftId', $this->shift->id)
        ->call('markStatus', $signup->id, 'on_time')
        ->assertSee('1/1');
});
