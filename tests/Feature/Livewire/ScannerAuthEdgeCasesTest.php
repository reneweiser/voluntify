<?php

use App\Livewire\ScannerAuth;
use App\Models\ProjectScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

// --- P1: Rate Limiting ---

it('rate limits after 5 failed attempts', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create();

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token]);

    for ($i = 0; $i < 5; $i++) {
        $component
            ->set('authCode', '000000')
            ->call('authenticate')
            ->assertSet('errorMessage', 'Invalid code. Please try again.');
    }

    $component
        ->set('authCode', '000000')
        ->call('authenticate');

    expect($component->get('errorMessage'))->toContain('Too many attempts');
});

it('blocks correct code when rate limited', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create();

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token]);

    for ($i = 0; $i < 5; $i++) {
        $component
            ->set('authCode', '000000')
            ->call('authenticate');
    }

    $component
        ->set('authCode', '123456')
        ->call('authenticate');

    expect($component->get('errorMessage'))->toContain('Too many attempts');
    $component->assertNoRedirect();
});

it('clears rate limiter on successful auth', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create();

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token]);

    // Fail 3 times
    for ($i = 0; $i < 3; $i++) {
        $component
            ->set('authCode', '000000')
            ->call('authenticate');
    }

    // Succeed
    $component
        ->set('authCode', '123456')
        ->call('authenticate')
        ->assertRedirect(route('scanner.app', $scanner->scanner_token));

    // Rate limiter should be cleared, so new attempts are allowed
    $rateLimitKey = 'scanner_auth:'.$scanner->scanner_token.':127.0.0.1';
    expect(RateLimiter::tooManyAttempts($rateLimitKey, 5))->toBeFalse();
});

it('clears auth code field on failed attempt', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create();

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', '000000')
        ->call('authenticate')
        ->assertSet('authCode', '');
});

it('clears auth code field when rate limited', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create();

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token]);

    for ($i = 0; $i < 6; $i++) {
        $component
            ->set('authCode', '000000')
            ->call('authenticate');
    }

    expect($component->get('authCode'))->toBe('');
});

// --- P5: Session flash for rawAuthCode (tested via ScannerManagement) ---

// --- Spec: Already authenticated redirects to app ---

it('redirects to scanner app when already authenticated in session', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    session(['scanner_id' => $scanner->id]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertRedirect(route('scanner.app', $scanner->scanner_token));
});

it('does not redirect when session has different scanner id', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    session(['scanner_id' => 99999]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertNoRedirect()
        ->assertOk();
});

// --- Scheduled scanner ---

it('shows scanner info for scheduled scanner without error', function () {
    $scanner = ProjectScanner::factory()->scheduled()->create([
        'name' => 'Entrance A',
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerName', 'Entrance A')
        ->assertSet('errorMessage', '');
});

// --- Validation ---

it('validates auth code is required', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', '')
        ->call('authenticate')
        ->assertHasErrors(['authCode' => 'required']);
});

it('validates auth code must be 6 digits', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', '12345')
        ->call('authenticate')
        ->assertHasErrors(['authCode' => 'digits']);
});

it('validates auth code must be numeric', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', 'abcdef')
        ->call('authenticate')
        ->assertHasErrors(['authCode' => 'digits']);
});

// --- Session data ---

it('stores scanner_authenticated_at in session on success', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create();

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', '123456')
        ->call('authenticate')
        ->assertSessionHas('scanner_authenticated_at');
});
