<?php

use App\Enums\AttendanceStatus;
use App\Enums\ScannerType;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->project = Project::factory()->create([
        'attendance_states' => Project::defaultAttendanceStates(),
    ]);
    $this->event = Event::factory()->for($this->project)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
    ]);
    $this->scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'entry_event_id' => $this->event->id,
        'pool_event_ids' => [$this->event->id],
        'type' => ScannerType::VolunteerAdmin,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('records attendance for valid shift signup', function () {
    $response = $this->postJson(
        route('scanner-api.attendance', $this->scanner->id),
        [
            'shift_signup_id' => $this->signup->id,
            'status' => 'on_time',
        ],
        ['X-Scanner-Token' => $this->scanner->scanner_token],
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('attendance_record.status', 'on_time');

    expect(AttendanceRecord::where('shift_signup_id', $this->signup->id)->exists())->toBeTrue();
});

it('returns 403 for entry staff scanner', function () {
    $entryScanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);

    $this->postJson(
        route('scanner-api.attendance', $entryScanner->id),
        [
            'shift_signup_id' => $this->signup->id,
            'status' => 'on_time',
        ],
        ['X-Scanner-Token' => $entryScanner->scanner_token],
    )->assertForbidden();
});

it('returns 403 for gear scanner', function () {
    $gearScanner = ProjectScanner::factory()->active()->gear()->create([
        'project_id' => $this->project->id,
        'entry_event_id' => $this->event->id,
        'pool_event_ids' => [$this->event->id],
    ]);

    $this->postJson(
        route('scanner-api.attendance', $gearScanner->id),
        [
            'shift_signup_id' => $this->signup->id,
            'status' => 'on_time',
        ],
        ['X-Scanner-Token' => $gearScanner->scanner_token],
    )->assertForbidden();
});

it('returns 422 for invalid status', function () {
    $this->postJson(
        route('scanner-api.attendance', $this->scanner->id),
        [
            'shift_signup_id' => $this->signup->id,
            'status' => 'totally_invalid',
        ],
        ['X-Scanner-Token' => $this->scanner->scanner_token],
    )->assertUnprocessable();
});

it('returns 422 for inactive status', function () {
    $states = $this->project->attendance_states;
    $states[2]['active'] = false; // en_route
    $this->project->update(['attendance_states' => $states]);

    $this->postJson(
        route('scanner-api.attendance', $this->scanner->id),
        [
            'shift_signup_id' => $this->signup->id,
            'status' => 'en_route',
        ],
        ['X-Scanner-Token' => $this->scanner->scanner_token],
    )->assertUnprocessable();
});

it('updates existing attendance record on re-post', function () {
    AttendanceRecord::factory()->create([
        'shift_signup_id' => $this->signup->id,
        'status' => AttendanceStatus::Late,
    ]);

    $this->postJson(
        route('scanner-api.attendance', $this->scanner->id),
        [
            'shift_signup_id' => $this->signup->id,
            'status' => 'on_time',
        ],
        ['X-Scanner-Token' => $this->scanner->scanner_token],
    )->assertOk();

    expect(AttendanceRecord::where('shift_signup_id', $this->signup->id)->count())->toBe(1);
    expect(AttendanceRecord::where('shift_signup_id', $this->signup->id)->first()->status)
        ->toBe(AttendanceStatus::OnTime);
});

it('scopes shift signup to scanner project', function () {
    $otherProject = Project::factory()->create();
    $otherEvent = Event::factory()->for($otherProject)->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create();
    $otherVolunteer = Volunteer::factory()->for($otherProject)->create();
    $otherSignup = ShiftSignup::factory()->create([
        'volunteer_id' => $otherVolunteer->id,
        'shift_id' => $otherShift->id,
    ]);

    $this->postJson(
        route('scanner-api.attendance', $this->scanner->id),
        [
            'shift_signup_id' => $otherSignup->id,
            'status' => 'on_time',
        ],
        ['X-Scanner-Token' => $this->scanner->scanner_token],
    )->assertNotFound();
});
