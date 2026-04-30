<?php

use App\Enums\ScannerMode;
use App\Enums\ScannerType;
use App\Livewire\ScannerApp;
use App\Models\ProjectScanner;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders ScannerApp for authenticated scanner session', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->withSession(['scanner_id' => $scanner->id])
        ->get(route('scanner.app', $scanner->scanner_token))
        ->assertOk()
        ->assertSeeLivewire(ScannerApp::class);
});

it('redirects unauthenticated request to ScannerAuth', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->get(route('scanner.app', $scanner->scanner_token))
        ->assertRedirect(route('scanner.auth', $scanner->scanner_token));
});

it('redirects expired scanner to ScannerAuth with error', function () {
    $scanner = ProjectScanner::factory()->expired()->create();

    $this->withSession(['scanner_id' => $scanner->id])
        ->get(route('scanner.app', $scanner->scanner_token))
        ->assertRedirect(route('scanner.auth', $scanner->scanner_token));
});

it('exposes correct scannerId and projectId', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerId', $scanner->id)
        ->assertSet('projectId', $scanner->project_id)
        ->assertOk();
});

it('loads entry staff scanner properties correctly', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
        'modes' => [ScannerMode::Checkin->value],
        'hint_text' => 'Scan tickets here',
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerType', ScannerType::EntryStaff->value)
        ->assertSet('modes', [ScannerMode::Checkin->value])
        ->assertSee('Scan tickets here');
});

it('renders manual volunteer lookup UI for entry staff scanners', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSee('Volunteers')
        ->assertSee('Search volunteers...')
        ->assertSeeHtml('volunteerSearchQuery')
        ->assertSeeHtml('selectVolunteerFromSearch(volunteer)')
        ->assertSee('Bereits eingecheckt')
        ->assertSee('Close Details');
});

it('loads volunteer admin scanner properties correctly', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerType', ScannerType::VolunteerAdmin->value)
        ->assertSet('modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value]);
});

it('does not render entry staff volunteer lookup UI for volunteer admin scanners', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertDontSee('Search volunteers...')
        ->assertDontSeeHtml('selectVolunteerFromSearch(volunteer)')
        ->assertDontSee('Close Details');
});

it('renders gear scanner tabs and guest search UI', function () {
    $scanner = ProjectScanner::factory()->active()->gear()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerType', ScannerType::Gear->value)
        ->assertSee('Volunteers')
        ->assertSee('Guests')
        ->assertSee('Search volunteers...')
        ->assertSee('Search guests...')
        ->assertSeeHtml('selectGuestFromSearch(entry)')
        ->assertSee('Record Pickup');
});

it('hides attendance and arrival actions for gear scanners', function () {
    $scanner = ProjectScanner::factory()->active()->gear()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertDontSeeHtml('confirmArrival()')
        ->assertDontSeeHtml('confirmAttendance(signup.id)')
        ->assertDontSeeHtml('confirmGuestCheckin(entry.id)');
});

it('renders scanner and shift-list tabs for volunteer admin scanners with checkin mode', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSee('Scanner')
        ->assertSee('Schichtliste')
        ->assertSeeHtml("setActiveTab('shifts')");
});

it('hides shifts section for VA scanner with gear_pickup only mode', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::VolunteerAdmin,
        'modes' => [ScannerMode::GearPickup->value],
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertDontSee(__('Shifts'));
});

it('does not render shift-list tab for gear-only volunteer admin scanners', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::VolunteerAdmin,
        'modes' => [ScannerMode::GearPickup->value],
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertDontSee('Schichtliste')
        ->assertDontSeeHtml('tabpanel-shifts');
});

it('shows shifts section for VA scanner with checkin mode', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::VolunteerAdmin,
        'modes' => [ScannerMode::Checkin->value],
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSee(__('Shifts'));
});

it('shows both shifts and gear for VA scanner with both modes', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSee(__('Shifts'))
        ->assertSee(__('Gear'));
});

it('hides gear section for VA scanner with checkin only mode', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::VolunteerAdmin,
        'modes' => [ScannerMode::Checkin->value],
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertDontSeeHtml('<h3 class="mb-3 text-sm font-semibold text-zinc-300">Gear</h3>');
});

it('does not render global arrival button for VA scanner', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertDontSeeHtml('confirmArrival()');
});

it('maps result state to emerald colors in entry staff template', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSeeHtml("'bg-emerald-500/10 border border-emerald-500/30': state === 'result'");
});

it('keeps loading state with zinc styling in entry staff template', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSeeHtml("'bg-zinc-800 border border-zinc-700': state === 'loading'");
});

it('includes confirm arrival button in entry staff result panel', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSeeHtml('confirmArrival()')
        ->assertSeeHtml(__('Confirm Arrival'));
});

it('includes dismiss button for terminal states in entry staff', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSeeHtml(__('Next Scan'));
});

it('renders phone display in entry staff template', function () {
    $scanner = ProjectScanner::factory()->active()->create([
        'type' => ScannerType::EntryStaff,
    ]);
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSeeHtml('selectedVolunteer?.phone')
        ->assertSee(__('No phone number'));
});

it('renders phone display in volunteer admin template', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSeeHtml('selectedVolunteer?.phone')
        ->assertSee(__('No phone number'));
});

it('renders shift-list states and shared scanner helpers in volunteer admin template', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSee('Mindestens 2 Zeichen eingeben.')
        ->assertSee('Offline - showing cached shift data.')
        ->assertSeeHtml('selectedVolunteerShiftSignups')
        ->assertSeeHtml('selectVolunteerFromShift(row)')
        ->assertSeeHtml('jumpToNextShift()');
});
