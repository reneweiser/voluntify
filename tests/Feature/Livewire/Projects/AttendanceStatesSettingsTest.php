<?php

use App\Enums\StaffRole;
use App\Livewire\Projects\AttendanceStatesSettings;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create([
        'attendance_states' => Project::defaultAttendanceStates(),
    ]);
    app()->instance(Organization::class, $this->org);
});

it('renders current attendance states', function () {
    Livewire::actingAs($this->organizer)
        ->test(AttendanceStatesSettings::class, ['projectId' => $this->project->id])
        ->assertSee('on_time')
        ->assertSee('late')
        ->assertSee('en_route')
        ->assertSee('excused')
        ->assertSee('no_show')
        ->assertSee('Attendance States');
});

it('allows renaming a state label', function () {
    Livewire::actingAs($this->organizer)
        ->test(AttendanceStatesSettings::class, ['projectId' => $this->project->id])
        ->set('states.0.label', 'Rechtzeitig')
        ->call('save')
        ->assertHasNoErrors();

    $this->project->refresh();
    expect($this->project->attendance_states[0]['label'])->toBe('Rechtzeitig');
});

it('prevents deactivating core states', function () {
    Livewire::actingAs($this->organizer)
        ->test(AttendanceStatesSettings::class, ['projectId' => $this->project->id])
        ->set('states.0.active', false) // on_time is core
        ->call('save')
        ->assertHasErrors('states.0.active');
});

it('allows deactivating non-core states', function () {
    Livewire::actingAs($this->organizer)
        ->test(AttendanceStatesSettings::class, ['projectId' => $this->project->id])
        ->set('states.1.active', false) // late is non-core
        ->call('save')
        ->assertHasNoErrors();

    $this->project->refresh();
    expect($this->project->attendance_states[1]['active'])->toBeFalse();
});

it('validates state data before saving', function () {
    Livewire::actingAs($this->organizer)
        ->test(AttendanceStatesSettings::class, ['projectId' => $this->project->id])
        ->set('states.0.label', '') // empty label
        ->call('save')
        ->assertHasErrors('states.0.label');
});

it('requires organizer authorization', function () {
    $volunteerAdmin = User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->get(route('projects.attendance-states', $this->project))
        ->assertForbidden();
});
