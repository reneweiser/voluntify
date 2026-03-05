<?php

use App\Actions\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
    ]);
    $this->recorder = User::factory()->create();
    $this->action = new RecordAttendance;
});

it('creates an attendance record', function () {
    $record = $this->action->execute($this->signup, AttendanceStatus::OnTime, $this->recorder);

    expect($record)->toBeInstanceOf(AttendanceRecord::class)
        ->and($record->shift_signup_id)->toBe($this->signup->id)
        ->and($record->status)->toBe(AttendanceStatus::OnTime)
        ->and($record->recorded_by)->toBe($this->recorder->id)
        ->and($record->recorded_at)->not->toBeNull();

    $this->assertDatabaseHas('attendance_records', [
        'shift_signup_id' => $this->signup->id,
        'status' => AttendanceStatus::OnTime->value,
    ]);
});

it('updates an existing record on re-mark (last-write-wins)', function () {
    $firstRecorder = User::factory()->create();
    $this->action->execute($this->signup, AttendanceStatus::OnTime, $firstRecorder);

    $record = $this->action->execute($this->signup, AttendanceStatus::Late, $this->recorder);

    expect($record->status)->toBe(AttendanceStatus::Late)
        ->and($record->recorded_by)->toBe($this->recorder->id);

    expect(AttendanceRecord::where('shift_signup_id', $this->signup->id)->count())->toBe(1);
});

it('does not set conflict flag when marking on time', function () {
    $record = $this->action->execute($this->signup, AttendanceStatus::OnTime, $this->recorder);

    expect($record->conflictDetected)->toBeFalse();
});

it('does not set conflict flag for no show without arrival', function () {
    $record = $this->action->execute($this->signup, AttendanceStatus::NoShow, $this->recorder);

    expect($record->conflictDetected)->toBeFalse();
});

it('sets conflict flag when marking no show but volunteer has arrival', function () {
    $ticket = Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
    ]);
    EventArrival::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    $record = $this->action->execute($this->signup, AttendanceStatus::NoShow, $this->recorder);

    expect($record->conflictDetected)->toBeTrue();
});

it('does not detect conflict for arrival at a different event', function () {
    $otherEvent = Event::factory()->for($this->org)->create();
    $ticket = Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $otherEvent->id,
    ]);
    EventArrival::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $otherEvent->id,
        'ticket_id' => $ticket->id,
    ]);

    $record = $this->action->execute($this->signup, AttendanceStatus::NoShow, $this->recorder);

    expect($record->conflictDetected)->toBeFalse();
});
