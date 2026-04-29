<?php

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Livewire\Projects\ScannerManagement;
use App\Livewire\ScannerApp;
use App\Livewire\ScannerAuth;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('completes full entry staff flow: create scanner, auth, fetch data, sync arrival', function () {
    // Setup
    ['user' => $organizer, 'organization' => $org] = createUserWithOrganization(StaffRole::Organizer);
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($project)->create();
    app()->instance(Organization::class, $org);

    // Step 1: Organizer creates a scanner via management UI
    Livewire::actingAs($organizer)
        ->test(ScannerManagement::class, ['projectId' => $project->id])
        ->set('form.name', 'Main Entrance')
        ->set('form.type', ScannerType::EntryStaff->value)
        ->set('form.modes', [ScannerMode::Checkin->value])
        ->set('form.entryEventId', $event->id)
        ->set('form.poolEventIds', [$event->id])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasNoErrors();

    $scanner = ProjectScanner::where('project_id', $project->id)->first();
    expect($scanner)->not->toBeNull()
        ->and($scanner->name)->toBe('Main Entrance');

    // Step 2: Scanner auth — authenticate with correct PIN
    $plainCode = '654321';
    $scanner->update(['auth_code' => Hash::make($plainCode)]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', $plainCode)
        ->call('authenticate')
        ->assertSessionHas('scanner_id', $scanner->id)
        ->assertRedirect(route('scanner.app', $scanner->scanner_token));

    // Step 3: Access scanner app with session
    session(['scanner_id' => $scanner->id]);
    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerId', $scanner->id)
        ->assertSet('projectId', $project->id)
        ->assertSet('scannerType', ScannerType::EntryStaff->value);

    // Step 4: Fetch scanner data via API
    $volunteer = Volunteer::factory()->create(['project_id' => $project->id]);
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $dataResponse = $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);
    $dataResponse->assertOk()
        ->assertJsonPath('scanner.id', $scanner->id)
        ->assertJsonPath('scanner.type', ScannerType::EntryStaff->value);

    // Step 5: Sync an arrival
    $syncResponse = $this->postJson(route('scanner-api.sync', $scanner->id), [
        'contract_version' => ProjectScanner::CONTRACT_VERSION,
        'arrivals' => [
            [
                'ticket_id' => $ticket->id,
                'event_id' => $event->id,
                'method' => 'qr_scan',
                'scanned_at' => now()->toISOString(),
            ],
        ],
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ]);

    $syncResponse->assertOk()
        ->assertJsonStructure(['arrivals']);

    expect(EventArrival::where('event_id', $event->id)->count())->toBe(1);
});

it('completes full volunteer admin flow: create scanner, auth, fetch data, gear pickup', function () {
    // Setup
    ['user' => $organizer, 'organization' => $org] = createUserWithOrganization(StaffRole::Organizer);
    $project = Project::factory()->for($org)->create();
    $event = Event::factory()->for($project)->create();
    app()->instance(Organization::class, $org);

    // Create gear item
    $gearItem = ProjectGearItem::factory()->create(['project_id' => $project->id]);

    // Step 1: Create VA scanner
    Livewire::actingAs($organizer)
        ->test(ScannerManagement::class, ['projectId' => $project->id])
        ->set('form.name', 'Gear Station')
        ->set('form.type', ScannerType::VolunteerAdmin->value)
        ->set('form.modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value])
        ->set('form.startsAt', '2026-07-01T10:00')
        ->set('form.endsAt', '2026-07-01T18:00')
        ->call('createScanner')
        ->assertHasNoErrors();

    $scanner = ProjectScanner::where('project_id', $project->id)->first();

    // Step 2: Auth
    $plainCode = '111111';
    $scanner->update(['auth_code' => Hash::make($plainCode)]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', $plainCode)
        ->call('authenticate')
        ->assertSessionHas('scanner_id', $scanner->id);

    // Step 3: Scanner app loaded with VA type
    session(['scanner_id' => $scanner->id]);
    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerType', ScannerType::VolunteerAdmin->value)
        ->assertSet('modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value]);

    // Step 4: Gear pickup via API
    $volunteer = Volunteer::factory()->create(['project_id' => $project->id]);
    $gear = VolunteerGear::factory()->create([
        'volunteer_id' => $volunteer->id,
        'project_gear_item_id' => $gearItem->id,
    ]);

    $this->postJson(route('scanner-api.gear-pickup', $scanner->id), [
        'volunteer_gear_id' => $gear->id,
        'state' => 'L',
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertOk()
        ->assertJsonPath('success', true);

    // Step 5: VA scanner cannot sync arrivals
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $this->postJson(route('scanner-api.sync', $scanner->id), [
        'contract_version' => ProjectScanner::CONTRACT_VERSION,
        'arrivals' => [
            [
                'ticket_id' => $ticket->id,
                'event_id' => $event->id,
                'method' => 'qr_scan',
                'scanned_at' => now()->toISOString(),
            ],
        ],
    ], [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertForbidden();
});
