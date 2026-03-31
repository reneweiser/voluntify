<?php

use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
    $this->ticket = Ticket::factory()->for($this->volunteer)->for($this->project, 'project')->create();
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
    $volunteer2 = Volunteer::factory()->for($this->project)->create();
    $ticket2 = Ticket::factory()->for($volunteer2)->for($this->project, 'project')->create();

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

it('rejects syncing a ticket from a different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherEvent = Event::factory()->for($this->org)->for($otherProject)->create();
    $otherVolunteer = Volunteer::factory()->for($otherProject)->create();
    $otherTicket = Ticket::factory()->for($otherVolunteer)->for($otherProject, 'project')->create();

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->postJson(route('scanner.sync', $this->event->id), [
            'arrivals' => [
                [
                    'ticket_id' => $otherTicket->id,
                    'method' => 'qr_scan',
                    'scanned_at' => '2025-06-15 10:00:00',
                ],
            ],
        ]);

    $response->assertNotFound();
    expect(EventArrival::count())->toBe(0);
});

it('returns 403 for unauthorized user', function () {
    $volunteerAdmin = User::factory()->create();
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
