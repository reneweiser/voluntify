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

// --- Scanner ID mismatch (data endpoint) ---

it('returns 403 when scanner ID in URL does not match token', function () {
    $scanner1 = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);
    $scanner2 = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
    ]);

    $this->getJson(route('scanner-api.data', $scanner2->id), [
        'X-Scanner-Token' => $scanner1->scanner_token,
    ])->assertForbidden();
});

// --- Scanner ID mismatch (sync endpoint) ---

it('returns 403 when scanner ID in sync URL does not match token', function () {
    $scanner1 = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);
    $scanner2 = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
    ]);

    $this->postJson(route('scanner-api.sync', $scanner2->id), [
        'contract_version' => ProjectScanner::CONTRACT_VERSION,
        'arrivals' => [],
    ], [
        'X-Scanner-Token' => $scanner1->scanner_token,
    ])->assertForbidden();
});

// --- Scanner ID mismatch (gear pickup endpoint) ---

it('returns 403 when scanner ID in gear-pickup URL does not match token', function () {
    $scanner1 = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);
    $scanner2 = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner2->id), [
        'volunteer_gear_id' => 1,
    ], [
        'X-Scanner-Token' => $scanner1->scanner_token,
    ])->assertForbidden();
});

// --- P2: IDOR on gear pickup — cross-project gear rejected ---

it('returns 404 when gear item belongs to different project', function () {
    $otherProject = Project::factory()->create();
    $otherGearItem = ProjectGearItem::factory()->create(['project_id' => $otherProject->id]);
    $otherVolunteer = Volunteer::factory()->create(['project_id' => $otherProject->id]);
    $otherGear = VolunteerGear::factory()->create([
        'volunteer_id' => $otherVolunteer->id,
        'project_gear_item_id' => $otherGearItem->id,
    ]);

    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => $otherGear->id,
        'state' => 'M',
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertNotFound();
});

it('accepts gear item belonging to same project', function () {
    $gearItem = ProjectGearItem::factory()->create(['project_id' => $this->project->id]);
    $volunteer = Volunteer::factory()->create(['project_id' => $this->project->id]);
    $job = VolunteerJob::factory()->create([
        'event_id' => $this->event->id,
    ]);
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $gear = VolunteerGear::factory()->create([
        'volunteer_id' => $volunteer->id,
        'project_gear_item_id' => $gearItem->id,
    ]);

    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->withPoolEvents($this->event, [$this->event->id])->create([
        'project_id' => $this->project->id,
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => $gear->id,
        'state' => 'L',
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertOk()
        ->assertJsonPath('success', true);
});

// --- Sync: ticket scoped to project ---

it('returns 404 when syncing arrival for ticket from different project', function () {
    $otherProject = Project::factory()->create();
    $otherVolunteer = Volunteer::factory()->create(['project_id' => $otherProject->id]);
    $otherTicket = Ticket::factory()->create([
        'project_id' => $otherProject->id,
        'volunteer_id' => $otherVolunteer->id,
    ]);

    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'entry_event_id' => $this->event->id,
        'pool_event_ids' => [$this->event->id],
        'type' => ScannerType::EntryStaff,
    ]);

    $this->postJson(route('scanner-api.sync', $scanner->id), [
        'contract_version' => ProjectScanner::CONTRACT_VERSION,
        'arrivals' => [
            [
                'ticket_id' => $otherTicket->id,
                'event_id' => $this->event->id,
                'method' => 'qr_scan',
                'scanned_at' => now()->toISOString(),
            ],
        ],
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertNotFound();
});

it('rejects arrival sync when the contract version is missing', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'entry_event_id' => $this->event->id,
        'pool_event_ids' => [$this->event->id],
        'type' => ScannerType::EntryStaff,
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
    ])->assertUnprocessable();
});

// --- Data: returns data scoped to scanner's configured pool ---

it('returns only configured pool events', function () {
    $event2 = Event::factory()->for($this->project)->create();

    $scanner = ProjectScanner::factory()->active()->forEntryEvent($this->event)->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk()
        ->assertJsonCount(1, 'events')
        ->assertJsonPath('events.0.id', $this->event->id);
});

// --- Data: multi-event pool returns all configured events ---

it('returns all configured pool events for a multi-event scanner', function () {
    $additionalEvents = Event::factory()->count(2)->for($this->project)->create();
    $poolEventIds = collect([$this->event->id])
        ->merge($additionalEvents->pluck('id'))
        ->all();

    $scanner = ProjectScanner::factory()->active()->withPoolEvents($this->event, $poolEventIds)->create([
        'project_id' => $this->project->id,
    ]);

    $response = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $response->assertOk();

    $response->assertJsonCount(count($poolEventIds), 'events');
});

// --- Gear pickup validation ---

it('validates volunteer_gear_id is required on gear-pickup', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create([
        'project_id' => $this->project->id,
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertUnprocessable();
});

// --- GearPickup mode enforcement ---

it('returns 403 when entry staff scanner without gear mode calls gear-pickup', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $this->project->id,
        'type' => ScannerType::EntryStaff,
        'modes' => ['checkin'],
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => 1,
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertForbidden();
});
