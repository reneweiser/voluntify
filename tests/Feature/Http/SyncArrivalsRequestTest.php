<?php

use App\Enums\ArrivalMethod;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\Volunteer;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->published()->create();
});

it('accepts valid arrivals payload', function () {
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('scanner.sync', $this->event), [
            'arrivals' => [
                [
                    'ticket_id' => $ticket->id,
                    'method' => ArrivalMethod::QrScan->value,
                    'scanned_at' => now()->toISOString(),
                ],
            ],
        ])
        ->assertOk();
});

it('rejects empty arrivals array', function () {
    $this->actingAs($this->user)
        ->postJson(route('scanner.sync', $this->event), [
            'arrivals' => [],
        ])
        ->assertJsonValidationErrors('arrivals');
});

it('rejects missing ticket_id', function () {
    $this->actingAs($this->user)
        ->postJson(route('scanner.sync', $this->event), [
            'arrivals' => [
                [
                    'method' => ArrivalMethod::QrScan->value,
                    'scanned_at' => now()->toISOString(),
                ],
            ],
        ])
        ->assertJsonValidationErrors('arrivals.0.ticket_id');
});

it('rejects invalid method', function () {
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->create([
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('scanner.sync', $this->event), [
            'arrivals' => [
                [
                    'ticket_id' => $ticket->id,
                    'method' => 'invalid_method',
                    'scanned_at' => now()->toISOString(),
                ],
            ],
        ])
        ->assertJsonValidationErrors('arrivals.0.method');
});

it('rejects non-existent ticket_id', function () {
    $this->actingAs($this->user)
        ->postJson(route('scanner.sync', $this->event), [
            'arrivals' => [
                [
                    'ticket_id' => 99999,
                    'method' => ArrivalMethod::QrScan->value,
                    'scanned_at' => now()->toISOString(),
                ],
            ],
        ])
        ->assertJsonValidationErrors('arrivals.0.ticket_id');
});
