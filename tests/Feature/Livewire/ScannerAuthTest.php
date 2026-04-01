<?php

use App\Livewire\ScannerAuth;
use App\Models\ProjectScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('renders ScannerAuth page for valid scanner_token', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    $this->get(route('scanner.auth', $scanner->scanner_token))
        ->assertOk()
        ->assertSeeLivewire(ScannerAuth::class);

    Carbon::setTestNow();
});

it('returns 404 for unknown scanner_token', function () {
    $this->get(route('scanner.auth', 'nonexistent-token'))
        ->assertNotFound();
});

it('shows window closed message when scanner is expired', function () {
    Carbon::setTestNow('2026-07-01 16:00:00');

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSee('window')
        ->assertSet('errorMessage', 'Scanner window has closed.');

    Carbon::setTestNow();
});

it('authenticates with correct code and redirects to scanner app', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $plainCode = '123456';

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make($plainCode),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', $plainCode)
        ->call('authenticate')
        ->assertRedirect(route('scanner.app', $scanner->scanner_token));

    Carbon::setTestNow();
});

it('shows error for wrong auth code', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make('123456'),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', '999999')
        ->call('authenticate')
        ->assertSet('errorMessage', 'Invalid code. Please try again.')
        ->assertNoRedirect();

    Carbon::setTestNow();
});

it('writes scanner_id to session on successful auth', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $plainCode = '654321';

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make($plainCode),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', $plainCode)
        ->call('authenticate')
        ->assertSessionHas('scanner_id', $scanner->id);

    Carbon::setTestNow();
});
