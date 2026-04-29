<?php

use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
use App\Models\VolunteerJob;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->for($this->project)->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns scanner data with valid token', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
    ]);

    $volunteer = Volunteer::factory()->create([
        'project_id' => $this->project->id,
    ]);
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'scanner' => ['id', 'type', 'modes'],
            'events',
            'volunteers',
            'arrivals',
            'attendance_records',
            'keys',
        ])
        ->assertJsonPath('scanner.id', $scanner->id)
        ->assertJsonPath('scanner.type', ScannerType::EntryStaff->value);
});

it('includes phone in volunteer data when phone exists', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => null,
    ]);

    Volunteer::factory()->create([
        'project_id' => $this->project->id,
        'phone' => '+49123456789',
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('volunteers.0.phone', '+49123456789');
});

it('includes null phone when volunteer has no phone', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => null,
    ]);

    Volunteer::factory()->create([
        'project_id' => $this->project->id,
        'phone' => null,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('volunteers.0.phone', null);
});

it('includes attendance_states in data response', function () {
    $this->project->update(['attendance_states' => Project::defaultAttendanceStates()]);

    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['attendance_states'])
        ->assertJsonCount(5, 'attendance_states');
});

it('returns volunteer shift payload fields required for volunteer admin shift list', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
    ]);

    $volunteer = Volunteer::factory()->create([
        'project_id' => $this->project->id,
        'first_name' => 'Lisa',
        'last_name' => 'Mueller',
        'email' => 'lisa@example.com',
        'phone' => '+4911111111',
    ]);

    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $job = VolunteerJob::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Stage Setup',
    ]);

    $shift = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'shift_date' => '2026-07-01',
        'starts_at' => Carbon::parse('2026-07-01 13:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 17:00:00'),
        'display_text' => 'Jul 01, 13:00 - 17:00',
    ]);

    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $this->postJson(route('scanner-api.attendance', $scanner->id), [
        'shift_signup_id' => $signup->id,
        'status' => 'on_time',
        'scanned_at' => now()->toISOString(),
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertOk();

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('volunteers.0.id', $volunteer->id)
        ->assertJsonPath('volunteers.0.first_name', 'Lisa')
        ->assertJsonPath('volunteers.0.last_name', 'Mueller')
        ->assertJsonPath('volunteers.0.email', 'lisa@example.com')
        ->assertJsonPath('volunteers.0.phone', '+4911111111')
        ->assertJsonPath('volunteers.0.shift_signups.0.id', $signup->id)
        ->assertJsonPath('volunteers.0.shift_signups.0.shift.id', $shift->id)
        ->assertJsonPath('volunteers.0.shift_signups.0.shift.event_id', $this->event->id)
        ->assertJsonPath('volunteers.0.shift_signups.0.shift.starts_at', Carbon::parse('2026-07-01 13:00:00')->toJSON())
        ->assertJsonPath('volunteers.0.shift_signups.0.shift.ends_at', Carbon::parse('2026-07-01 17:00:00')->toJSON())
        ->assertJsonPath('volunteers.0.shift_signups.0.shift.display_text', 'Jul 01, 13:00 - 17:00')
        ->assertJsonPath('volunteers.0.shift_signups.0.shift.volunteer_job.name', 'Stage Setup')
        ->assertJsonPath('volunteers.0.shift_signups.0.attendance_record.status', 'on_time');
});

it('includes project-wide volunteers from past events and volunteers without shifts', function () {
    $pastEvent = Event::factory()->for($this->project)->create([
        'name' => 'Past Event',
        'starts_at' => Carbon::parse('2025-07-01 12:00:00'),
        'ends_at' => Carbon::parse('2025-07-01 18:00:00'),
    ]);

    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => null,
        'type' => ScannerType::EntryStaff,
    ]);

    $pastVolunteer = Volunteer::factory()->create([
        'project_id' => $this->project->id,
        'first_name' => 'Past',
        'last_name' => 'Volunteer',
        'email' => 'past-volunteer@example.com',
    ]);
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $pastVolunteer->id,
    ]);

    $pastJob = VolunteerJob::factory()->create([
        'event_id' => $pastEvent->id,
        'name' => 'Legacy Shift',
    ]);

    $pastShift = Shift::factory()->create([
        'volunteer_job_id' => $pastJob->id,
        'shift_date' => '2025-07-01',
        'starts_at' => Carbon::parse('2025-07-01 13:00:00'),
        'ends_at' => Carbon::parse('2025-07-01 15:00:00'),
        'display_text' => 'Jul 01, 13:00 - 15:00',
    ]);

    ShiftSignup::factory()->create([
        'volunteer_id' => $pastVolunteer->id,
        'shift_id' => $pastShift->id,
    ]);

    $noShiftVolunteer = Volunteer::factory()->create([
        'project_id' => $this->project->id,
        'first_name' => 'NoShift',
        'last_name' => 'Volunteer',
        'email' => 'noshift@example.com',
    ]);
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $noShiftVolunteer->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $pastVolunteerPayload = collect($response->json('volunteers'))
        ->firstWhere('id', $pastVolunteer->id);

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $pastVolunteer->id,
            'first_name' => 'Past',
            'last_name' => 'Volunteer',
            'email' => 'past-volunteer@example.com',
        ])
        ->assertJsonFragment([
            'id' => $noShiftVolunteer->id,
            'first_name' => 'NoShift',
            'last_name' => 'Volunteer',
            'email' => 'noshift@example.com',
        ]);

    expect($pastVolunteerPayload)->not->toBeNull();
    expect($pastVolunteerPayload['shift_signups'][0]['shift']['event_id'])->toBe($pastEvent->id);
});

it('returns default states for projects with null attendance_states', function () {
    $this->project->update(['attendance_states' => null]);

    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(5, 'attendance_states');
});

it('returns 401 for expired scanner token', function () {
    $scanner = ProjectScanner::factory()->expired()->create([
        'project_id' => $this->project->id,
    ]);

    $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertUnauthorized();
});

it('returns 401 without token', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);

    $this->getJson(route('scanner-api.data', $scanner->id))
        ->assertUnauthorized();
});

it('syncs arrivals for entry staff scanner', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'type' => ScannerType::EntryStaff,
    ]);

    $volunteer = Volunteer::factory()->create(['project_id' => $this->project->id]);
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $response = $this->postJson(route('scanner-api.sync', $scanner->id), [
        'arrivals' => [
            [
                'ticket_id' => $ticket->id,
                'event_id' => $this->event->id,
                'method' => 'qr_scan',
                'scanned_at' => now()->toISOString(),
            ],
        ],
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['arrivals']);
});

it('returns 403 when VA scanner tries to sync arrivals', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
    ]);

    $volunteer = Volunteer::factory()->create(['project_id' => $this->project->id]);
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $this->postJson(route('scanner-api.sync', $scanner->id), [
        'arrivals' => [
            [
                'ticket_id' => $ticket->id,
                'event_id' => $this->event->id,
                'method' => 'qr_scan',
                'scanned_at' => now()->toISOString(),
            ],
        ],
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertForbidden();
});

it('records gear pickup for VA scanner', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $volunteer = Volunteer::factory()->create(['project_id' => $this->project->id]);
    $gearItem = ProjectGearItem::factory()->create(['project_id' => $this->project->id]);
    $gear = VolunteerGear::factory()->create([
        'volunteer_id' => $volunteer->id,
        'project_gear_item_id' => $gearItem->id,
    ]);

    $response = $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => $gear->id,
        'state' => 'M',
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
});

it('returns 403 when entry staff scanner tries gear pickup', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => 1,
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertForbidden();
});

it('returns gear_items for volunteer admin scanner', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
    ]);
    $gearItem = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'gear_items')
        ->assertJsonPath('gear_items.0.id', $gearItem->id)
        ->assertJsonPath('gear_items.0.name', $gearItem->name)
        ->assertJsonPath('gear_items.0.available_sizes', ['S', 'M', 'L']);
});

it('returns volunteer_gear keyed by volunteer id for VA scanner', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
        'event_id' => null,
    ]);
    $volunteer = Volunteer::factory()->create(['project_id' => $this->project->id]);
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $volunteer->id,
    ]);
    $gearItem = ProjectGearItem::factory()->create(['project_id' => $this->project->id]);
    $gear = VolunteerGear::factory()->create([
        'volunteer_id' => $volunteer->id,
        'project_gear_item_id' => $gearItem->id,
        'size' => 'M',
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk();
    $volunteerGear = $response->json('volunteer_gear.'.$volunteer->id);
    expect($volunteerGear)->toHaveCount(1)
        ->and($volunteerGear[0]['id'])->toBe($gear->id)
        ->and($volunteerGear[0]['size'])->toBe('M');
});

it('returns empty gear data for entry staff scanner', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'type' => ScannerType::EntryStaff,
    ]);
    ProjectGearItem::factory()->create(['project_id' => $this->project->id]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk();
    expect($response->json('gear_items'))->toBeEmpty()
        ->and($response->json('volunteer_gear'))->toBeEmpty();
});

it('returns volunteer gear with pickup information', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
        'event_id' => null,
    ]);
    $volunteer = Volunteer::factory()->create(['project_id' => $this->project->id]);
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'volunteer_id' => $volunteer->id,
    ]);
    $gearItem = ProjectGearItem::factory()->create(['project_id' => $this->project->id]);
    $gear = VolunteerGear::factory()->create([
        'volunteer_id' => $volunteer->id,
        'project_gear_item_id' => $gearItem->id,
    ]);
    VolunteerGearPickup::factory()->create([
        'volunteer_gear_id' => $gear->id,
        'state' => 'M',
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk();
    $volunteerGear = $response->json('volunteer_gear.'.$volunteer->id);
    expect($volunteerGear[0]['picked_up'])->toBeTrue()
        ->and($volunteerGear[0]['pickups'])->toHaveCount(1)
        ->and($volunteerGear[0]['pickups'][0]['state'])->toBe('M');
});

it('includes quantity_entitled in volunteer gear payload for Typ 2 gear', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
        'event_id' => null,
    ]);

    $item = ProjectGearItem::factory()->quantity(3)->for($this->project)->create();
    $volunteer = Volunteer::factory()->for($this->project)->create();
    VolunteerGear::factory()->withQuantity(3)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk();
    $volunteerGear = $response->json('volunteer_gear.'.$volunteer->id);
    expect($volunteerGear[0]['quantity_entitled'])->toBe(3);
});

it('includes quantity_per_volunteer in gear items payload', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'type' => ScannerType::VolunteerAdmin,
    ]);

    ProjectGearItem::factory()->quantity(5)->for($this->project)->create();

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk();
    $gearItems = $response->json('gear_items');
    expect($gearItems[0]['quantity_per_volunteer'])->toBe(5);
});

it('rejects gear pickup when quantity exceeded via scanner API', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'type' => ScannerType::VolunteerAdmin,
    ]);

    $item = ProjectGearItem::factory()->quantity(1)->for($this->project)->create();
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $gear = VolunteerGear::factory()->withQuantity(1)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    $response = $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => $gear->id,
        'quantity' => 1,
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Pickup would exceed entitled quantity.');
});

it('allows gear pickup within quantity limit via scanner API', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'type' => ScannerType::VolunteerAdmin,
    ]);

    $item = ProjectGearItem::factory()->quantity(3)->for($this->project)->create();
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $gear = VolunteerGear::factory()->withQuantity(3)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $response = $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => $gear->id,
        'quantity' => 1,
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
});
