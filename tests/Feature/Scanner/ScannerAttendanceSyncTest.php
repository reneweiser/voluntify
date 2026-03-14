<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    Ticket::factory()->for($this->volunteer)->for($this->event)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
    ]);
});

it('syncs attendance records for organizer', function () {
    $response = $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.attendance-sync', $this->event->id), [
            'attendance' => [
                [
                    'shift_signup_id' => $this->signup->id,
                    'status' => 'on_time',
                    'scanned_at' => now()->toDateTimeString(),
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->json('attendance_records'))->toHaveCount(1);

    $record = AttendanceRecord::where('shift_signup_id', $this->signup->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(AttendanceStatus::OnTime);
});

it('syncs attendance records for volunteer admin', function () {
    $this->actingAs($this->volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.attendance-sync', $this->event->id), [
            'attendance' => [
                [
                    'shift_signup_id' => $this->signup->id,
                    'status' => 'late',
                    'scanned_at' => now()->toDateTimeString(),
                ],
            ],
        ])
        ->assertOk();

    $record = AttendanceRecord::where('shift_signup_id', $this->signup->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Late);
});

it('returns 403 for entrance staff', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.attendance-sync', $this->event->id), [
            'attendance' => [
                [
                    'shift_signup_id' => $this->signup->id,
                    'status' => 'on_time',
                    'scanned_at' => now()->toDateTimeString(),
                ],
            ],
        ])
        ->assertForbidden();
});

it('validates input', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.attendance-sync', $this->event->id), [
            'attendance' => [
                ['shift_signup_id' => 99999, 'status' => 'invalid_status', 'scanned_at' => 'not-a-date'],
            ],
        ])
        ->assertUnprocessable();
});

it('scopes signups to event', function () {
    $otherEvent = Event::factory()->for($this->org)->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create();
    $otherSignup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $otherShift->id,
    ]);

    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.attendance-sync', $this->event->id), [
            'attendance' => [
                [
                    'shift_signup_id' => $otherSignup->id,
                    'status' => 'on_time',
                    'scanned_at' => now()->toDateTimeString(),
                ],
            ],
        ])
        ->assertNotFound();
});
