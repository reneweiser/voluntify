<?php

use App\Concerns\ConvertsTimezone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

// Create a test class that uses the trait
beforeEach(function () {
    $this->converter = new class
    {
        use ConvertsTimezone;

        public function callToUtc(string $localDatetime, string $timezone): CarbonImmutable
        {
            return $this->toUtc($localDatetime, $timezone);
        }

        public function callToLocal(CarbonInterface $utcDatetime, string $timezone): string
        {
            return $this->toLocal($utcDatetime, $timezone);
        }
    };
});

it('converts local datetime to UTC', function () {
    // 11:43 in Berlin (CEST, UTC+2) = 09:43 UTC
    $result = $this->converter->callToUtc('2026-07-01T11:43', 'Europe/Berlin');

    expect($result->timezone->getName())->toBe('UTC')
        ->and($result->format('Y-m-d H:i'))->toBe('2026-07-01 09:43');
});

it('converts UTC datetime to local string', function () {
    $utc = CarbonImmutable::parse('2026-07-01 09:43:00', 'UTC');

    $result = $this->converter->callToLocal($utc, 'Europe/Berlin');

    expect($result)->toBe('2026-07-01T11:43');
});

it('handles UTC timezone as no-op', function () {
    $result = $this->converter->callToUtc('2026-07-01T11:43', 'UTC');

    expect($result->format('Y-m-d H:i'))->toBe('2026-07-01 11:43');
});

it('handles DST spring-forward by rolling to next valid time', function () {
    // March 29 2026 at 02:00, clocks spring forward to 03:00 in Europe/Berlin
    // 02:30 doesn't exist — Carbon rolls to 03:30 CEST (= 01:30 UTC)
    $result = $this->converter->callToUtc('2026-03-29T02:30', 'Europe/Berlin');

    expect($result->format('Y-m-d H:i'))->toBe('2026-03-29 01:30');
});

it('handles DST fall-back with second occurrence', function () {
    // October 25 2026 at 03:00, clocks fall back to 02:00 in Europe/Berlin
    // 02:30 is ambiguous — Carbon defaults to the second occurrence (CET = UTC+1)
    $result = $this->converter->callToUtc('2026-10-25T02:30', 'Europe/Berlin');

    // CET interpretation: 02:30 CET = 01:30 UTC
    expect($result->format('Y-m-d H:i'))->toBe('2026-10-25 01:30');
});

it('throws on empty string input', function () {
    $this->converter->callToUtc('', 'Europe/Berlin');
})->throws(InvalidArgumentException::class);

it('throws on malformed datetime input', function () {
    $this->converter->callToUtc('not-a-date', 'Europe/Berlin');
})->throws(InvalidArgumentException::class);

it('round-trips correctly through toUtc and toLocal', function () {
    $original = '2026-07-01T14:30';
    $timezone = 'America/New_York';

    $utc = $this->converter->callToUtc($original, $timezone);
    $roundTripped = $this->converter->callToLocal($utc, $timezone);

    expect($roundTripped)->toBe($original);
});
