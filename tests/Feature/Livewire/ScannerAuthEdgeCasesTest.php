<?php

use App\Livewire\ScannerAuth;
use App\Models\ProjectScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
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
            ->assertSet('errorMessage', 'Ungültiger Code. Bitte versuche es erneut.');
    }

    $component
        ->set('authCode', '000000')
        ->call('authenticate');

    expect($component->get('errorMessage'))->toContain('Zu viele Versuche');
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

    expect($component->get('errorMessage'))->toContain('Zu viele Versuche');
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

    // Per-session rate limiter should be cleared after successful auth
    $sessionKey = 'scanner_auth:'.$scanner->scanner_token.':'.session()->getId();
    expect(RateLimiter::tooManyAttempts($sessionKey, 5))->toBeFalse();
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

it('shows not-yet-active message and disables form for scheduled scanner', function () {
    $scanner = ProjectScanner::factory()->scheduled()->create([
        'name' => 'Entrance A',
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('scannerName', 'Entrance A')
        ->assertSet('formDisabled', true)
        ->assertSee('noch nicht aktiv');
});

it('shows not-yet-active message with start time', function () {
    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 14:30:00'),
        'ends_at' => Carbon::parse('2026-07-01 18:00:00'),
    ]);

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('formDisabled', true)
        ->assertSee('14:30');
});

it('shows distinct error when scanner expires during auth attempt', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create([
        'ends_at' => Carbon::parse('2026-07-01 12:05:00'),
    ]);

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('formDisabled', false);

    // Advance time past window end
    Carbon::setTestNow('2026-07-01 12:10:00');

    $component
        ->set('authCode', '123456')
        ->call('authenticate')
        ->assertSet('formDisabled', true);

    expect($component->get('errorMessage'))->toContain('abgelaufen');
});

it('does not bypass rate limit when scanner is not active', function () {
    $scanner = ProjectScanner::factory()->scheduled()->withAuthCode('123456')->create();

    Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->set('authCode', '000000')
        ->call('authenticate');

    $globalKey = 'scanner_auth_global:'.$scanner->scanner_token;
    expect(RateLimiter::attempts($globalKey))->toBeGreaterThan(0);
});

it('succeeds after scanner transitions from scheduled to active', function () {
    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make('123456'),
        'starts_at' => Carbon::parse('2026-07-01 13:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 17:00:00'),
    ]);

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token])
        ->assertSet('formDisabled', true);

    // Advance time into active window
    Carbon::setTestNow('2026-07-01 13:30:00');

    $component
        ->set('authCode', '123456')
        ->call('authenticate')
        ->assertRedirect(route('scanner.app', $scanner->scanner_token));
});

it('shows timing message instead of rate limit message when scanner is not active', function () {
    $scanner = ProjectScanner::factory()->active()->withAuthCode('123456')->create([
        'ends_at' => Carbon::parse('2026-07-01 12:30:00'),
    ]);

    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $scanner->scanner_token]);

    // Exhaust rate limiter with wrong codes
    for ($i = 0; $i < 5; $i++) {
        $component
            ->set('authCode', '000000')
            ->call('authenticate');
    }

    // Scanner expires
    Carbon::setTestNow('2026-07-01 13:00:00');

    $component
        ->set('authCode', '123456')
        ->call('authenticate')
        ->assertSet('formDisabled', true);

    expect($component->get('errorMessage'))->toContain('abgelaufen');
    expect($component->get('errorMessage'))->not->toContain('Zu viele Versuche');
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
