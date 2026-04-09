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

it('loads volunteer admin scanner properties correctly', function () {
    $scanner = ProjectScanner::factory()->active()->volunteerAdmin()->create();
    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerApp::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerType', ScannerType::VolunteerAdmin->value)
        ->assertSet('modes', [ScannerMode::Checkin->value, ScannerMode::GearPickup->value]);
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
