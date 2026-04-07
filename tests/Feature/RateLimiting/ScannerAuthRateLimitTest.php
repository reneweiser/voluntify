<?php

use App\Enums\ActivityCategory;
use App\Livewire\ScannerAuth;
use App\Models\ActivityLog;
use App\Models\ProjectScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $this->scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make('123456'),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    RateLimiter::clear('scanner_auth_global:'.$this->scanner->scanner_token);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows up to 5 failed attempts before lockout', function () {
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);

    for ($i = 0; $i < 5; $i++) {
        $component->set('authCode', '999999')->call('authenticate');
    }

    $component->set('authCode', '999999')->call('authenticate');

    expect($component->get('errorMessage'))->toContain('Zu viele Versuche');
});

it('locks out for 30 minutes after 5 failed attempts', function () {
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);

    for ($i = 0; $i < 5; $i++) {
        $component->set('authCode', '999999')->call('authenticate');
    }

    $component->set('authCode', '123456')->call('authenticate');

    expect($component->get('errorMessage'))->toContain('Zu viele Versuche');
    $component->assertNoRedirect();
});

it('creates activity log entry on scanner lockout', function () {
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);

    for ($i = 0; $i < 5; $i++) {
        $component->set('authCode', '999999')->call('authenticate');
    }

    $log = ActivityLog::where('action', 'lockout')
        ->where('category', ActivityCategory::System)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['scanner_name'])->toBe($this->scanner->name);
});

it('allows login after lockout expires', function () {
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);

    for ($i = 0; $i < 5; $i++) {
        $component->set('authCode', '999999')->call('authenticate');
    }

    // Advance past the 30-minute lockout
    Carbon::setTestNow(now()->addMinutes(31));

    $component->set('authCode', '123456')->call('authenticate');

    $component->assertRedirect(route('scanner.app', $this->scanner->scanner_token));
});

it('clears rate limit on successful auth', function () {
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);

    // Fail 4 times (one away from lockout), then succeed
    for ($i = 0; $i < 4; $i++) {
        $component->set('authCode', '999999')->call('authenticate');
    }
    $component->set('authCode', '123456')->call('authenticate');

    $component->assertRedirect(route('scanner.app', $this->scanner->scanner_token));

    // Flush session to simulate a fresh visit (session()->regenerate() in authenticate changed the ID)
    session()->flush();
    session()->regenerate(true);

    // A new session should be able to fail without hitting lockout — proves global key didn't overflow
    $freshComponent = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);
    $freshComponent->set('authCode', '999999')->call('authenticate');
    expect($freshComponent->get('errorMessage'))->toBe('Ungültiger Code. Bitte versuche es erneut.');
});

it('rate limits sessions independently', function () {
    // Session A: exhaust 5 attempts → locked out
    $componentA = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);
    for ($i = 0; $i < 5; $i++) {
        $componentA->set('authCode', '999999')->call('authenticate');
    }
    $componentA->set('authCode', '999999')->call('authenticate');
    expect($componentA->get('errorMessage'))->toContain('Zu viele Versuche');

    // Simulate a different user with a fresh session
    session()->flush();
    session()->regenerate(true);

    // Session B: fresh session — should NOT be locked out
    $componentB = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);
    $componentB->set('authCode', '123456')->call('authenticate');
    $componentB->assertRedirect(route('scanner.app', $this->scanner->scanner_token));
});

it('locks out the scanner globally after 15 failed attempts across sessions', function () {
    // 15 failed attempts across separate sessions (regenerate session each time)
    for ($i = 0; $i < 15; $i++) {
        session()->flush();
        session()->regenerate(true);
        $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);
        $component->set('authCode', '999999')->call('authenticate');
    }

    // New session with correct code should still be blocked by global limit
    session()->flush();
    session()->regenerate(true);
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);
    $component->set('authCode', '123456')->call('authenticate');
    expect($component->get('errorMessage'))->toContain('Zu viele Versuche');
    $component->assertNoRedirect();
});
