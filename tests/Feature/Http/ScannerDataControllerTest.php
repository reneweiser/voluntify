<?php

use App\Enums\ScannerType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
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
