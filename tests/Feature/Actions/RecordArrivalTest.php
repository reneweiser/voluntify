<?php

use App\Actions\RecordArrival;
use App\Enums\ArrivalMethod;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use Carbon\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->ticket = Ticket::factory()->for($this->volunteer)->for($this->event)->create();
    $this->scanner = User::factory()->create();
    $this->action = app(RecordArrival::class);
});

// C1: Happy Path

it('creates arrival record via QR scan', function () {
    $arrival = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
    );

    expect($arrival)->toBeInstanceOf(EventArrival::class)
        ->and($arrival->ticket_id)->toBe($this->ticket->id)
        ->and($arrival->volunteer_id)->toBe($this->volunteer->id)
        ->and($arrival->event_id)->toBe($this->event->id)
        ->and($arrival->scanned_by)->toBe($this->scanner->id)
        ->and($arrival->method)->toBe(ArrivalMethod::QrScan)
        ->and($arrival->flagged)->toBeFalse();
});

it('creates arrival record via manual lookup', function () {
    $arrival = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::ManualLookup,
    );

    expect($arrival->method)->toBe(ArrivalMethod::ManualLookup);
});

it('uses now() as default scanned_at', function () {
    Carbon::setTestNow('2025-06-15 10:30:00');

    $arrival = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
    );

    expect($arrival->scanned_at->toDateTimeString())->toBe('2025-06-15 10:30:00');

    Carbon::setTestNow();
});

// C2: Duplicate Detection

it('returns flagged duplicate on re-scan', function () {
    $first = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
    );

    $second = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
    );

    expect($first->flagged)->toBeFalse()
        ->and($second->flagged)->toBeTrue()
        ->and($second->flag_reason)->toContain('Duplicate')
        ->and(EventArrival::count())->toBe(2);
});

it('preserves original scan time on duplicate', function () {
    Carbon::setTestNow('2025-06-15 10:00:00');
    $first = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
    );

    Carbon::setTestNow('2025-06-15 10:30:00');
    $second = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
    );

    expect($first->fresh()->scanned_at->toDateTimeString())->toBe('2025-06-15 10:00:00')
        ->and($second->scanned_at->toDateTimeString())->toBe('2025-06-15 10:30:00');

    Carbon::setTestNow();
});

// C3: Offline Timestamp

it('uses provided scanned_at for offline sync', function () {
    $offlineTime = Carbon::parse('2025-06-15 09:45:00');

    $arrival = $this->action->execute(
        ticket: $this->ticket,
        scannedBy: $this->scanner,
        method: ArrivalMethod::QrScan,
        scannedAt: $offlineTime,
    );

    expect($arrival->scanned_at->toDateTimeString())->toBe('2025-06-15 09:45:00');
});
