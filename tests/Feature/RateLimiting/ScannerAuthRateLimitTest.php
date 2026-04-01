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

    RateLimiter::clear('scanner_auth:'.$this->scanner->scanner_token);
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

    RateLimiter::clear('scanner_auth:'.$this->scanner->scanner_token);

    $component->set('authCode', '123456')->call('authenticate');

    $component->assertRedirect(route('scanner.app', $this->scanner->scanner_token));
});

it('clears rate limit on successful auth', function () {
    $component = Livewire::test(ScannerAuth::class, ['scannerToken' => $this->scanner->scanner_token]);

    $component->set('authCode', '999999')->call('authenticate');
    $component->set('authCode', '999999')->call('authenticate');
    $component->set('authCode', '123456')->call('authenticate');

    $component->assertRedirect(route('scanner.app', $this->scanner->scanner_token));

    expect(RateLimiter::tooManyAttempts('scanner_auth:'.$this->scanner->scanner_token, 5))->toBeFalse();
});
