<?php

use App\Enums\StaffRole;
use App\Livewire\Events\ManualEnrollment;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    app()->instance(Organization::class, $this->org);
});

it('denies access for non-organizers', function () {
    $volunteerAdmin = User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('renders for organizers', function () {
    Livewire::actingAs($this->organizer)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->assertOk()
        ->assertSee('Manual Enrollment');
});

it('searches volunteers by name', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Tester']);
    // Need volunteer to be associated with event via shift signup
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->set('search', 'Alice')
        ->assertSee('Alice Tester');
});

it('enrolls a volunteer into selected shifts', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Bob', 'last_name' => 'Enroll']);
    $job = VolunteerJob::factory()->for($this->event)->create();
    $existingShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);
    // Create a signup so volunteer appears in forEvent scope
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $existingShift->id]);

    $newShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);

    Livewire::actingAs($this->organizer)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->call('selectVolunteer', $volunteer->id)
        ->set('selectedShifts', [$newShift->id])
        ->call('enroll')
        ->assertSee('1 shift(s) enrolled successfully.');

    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->where('shift_id', $newShift->id)->exists())->toBeTrue();
});

it('skips full shifts', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $existingShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $existingShift->id]);

    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 1]);

    // Fill the shift
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['volunteer_id' => $otherVolunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->call('selectVolunteer', $volunteer->id)
        ->set('selectedShifts', [$shift->id])
        ->call('enroll')
        ->assertSee('1 shift(s) skipped (full).');
});

it('skips duplicate enrollments', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);

    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->call('selectVolunteer', $volunteer->id)
        ->set('selectedShifts', [$shift->id])
        ->call('enroll')
        ->assertSee('1 shift(s) skipped (already enrolled).');
});

it('suppresses notification when toggle is off', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $existingShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $existingShift->id]);

    $newShift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 5]);

    Livewire::actingAs($this->organizer)
        ->test(ManualEnrollment::class, ['eventId' => $this->event->id])
        ->call('selectVolunteer', $volunteer->id)
        ->set('selectedShifts', [$newShift->id])
        ->set('sendNotification', false)
        ->call('enroll');

    Notification::assertNothingSent();
});
