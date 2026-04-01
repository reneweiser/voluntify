<?php

use App\Actions\AuthenticateScanner;
use App\Models\ProjectScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

it('returns true for correct code within active window', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $plainCode = '123456';

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make($plainCode),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    $action = new AuthenticateScanner;
    $result = $action->execute($scanner, $plainCode);

    expect($result)->toBeTrue();

    Carbon::setTestNow();
});

it('returns false for wrong code', function () {
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make('123456'),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    $action = new AuthenticateScanner;
    $result = $action->execute($scanner, '999999');

    expect($result)->toBeFalse();

    Carbon::setTestNow();
});

it('returns false for expired window', function () {
    Carbon::setTestNow('2026-07-01 16:00:00');
    $plainCode = '123456';

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make($plainCode),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    $action = new AuthenticateScanner;
    $result = $action->execute($scanner, $plainCode);

    expect($result)->toBeFalse();

    Carbon::setTestNow();
});

it('returns false for scheduled window not yet started', function () {
    Carbon::setTestNow('2026-07-01 08:00:00');
    $plainCode = '123456';

    $scanner = ProjectScanner::factory()->create([
        'auth_code' => Hash::make($plainCode),
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);

    $action = new AuthenticateScanner;
    $result = $action->execute($scanner, $plainCode);

    expect($result)->toBeFalse();

    Carbon::setTestNow();
});
