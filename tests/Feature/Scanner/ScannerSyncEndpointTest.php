<?php

use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Ticket;
use App\Models\Volunteer;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->event = Event::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->create();
    $this->ticket = Ticket::factory()->for($this->volunteer)->for($this->event)->create();
});

it('syncs a single arrival', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(1);

    $arrival = EventArrival::first();
    expect($arrival->ticket_id)->toBe($this->ticket->id)
        ->and($arrival->volunteer_id)->toBe($this->volunteer->id)
        ->and($arrival->event_id)->toBe($this->event->id)
        ->and($arrival->method)->toBe(ArrivalMethod::QrScan)
        ->and($arrival->scanned_at->toDateTimeString())->toBe('2025-06-15 10:00:00');
});

it('syncs batch of arrivals', function () {
    $volunteer2 = Volunteer::factory()->create();
    $ticket2 = Ticket::factory()->for($volunteer2)->for($this->event)->create();

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
                [
                    'ticket_id' => $ticket2->id,
                    'method' => 'manual_lookup',
                    'scanned_at' => '2025-06-15 10:05:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(2);
});

it('handles duplicate arrivals gracefully', function () {
    // First sync
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    // Second sync with same ticket
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:30:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect(EventArrival::count())->toBe(2);

    $flagged = EventArrival::where('flagged', true)->first();
    expect($flagged)->not->toBeNull();
});

it('returns updated arrivals after sync', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->json('arrivals'))->toHaveCount(1);
});

it('validates input', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => null,
                    'method' => 'invalid_method',
                ],
            ],
        ])
        ->assertUnprocessable();
});

it('returns 403 for unauthorized user', function () {
    $volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $this->ticket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ])
        ->assertForbidden();
});
