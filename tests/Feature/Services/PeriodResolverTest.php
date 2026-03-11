<?php

use App\Services\PeriodResolver;
use Carbon\Carbon;

beforeEach(function () {
    $this->resolver = new PeriodResolver;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('currentPeriodDate returns today when after 4am', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');

    expect($this->resolver->currentPeriodDate())->toBe('2025-06-15');
});

it('currentPeriodDate returns yesterday when before 4am', function () {
    Carbon::setTestNow('2025-06-15 03:59:00');

    expect($this->resolver->currentPeriodDate())->toBe('2025-06-14');
});

it('previousPeriodDate returns yesterday when after 4am', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');

    expect($this->resolver->previousPeriodDate())->toBe('2025-06-14');
});

it('previousPeriodDate returns two days ago when before 4am', function () {
    Carbon::setTestNow('2025-06-15 03:59:00');

    expect($this->resolver->previousPeriodDate())->toBe('2025-06-13');
});

it('midnight exactly uses previous day period', function () {
    Carbon::setTestNow('2025-06-15 00:00:00');

    expect($this->resolver->currentPeriodDate())->toBe('2025-06-14')
        ->and($this->resolver->previousPeriodDate())->toBe('2025-06-13');
});
