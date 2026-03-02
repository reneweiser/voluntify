<?php

use App\Enums\ArrivalMethod;
use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Services\JwtKeyService;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->event = Event::factory()->for($this->org)->create();
});

it('returns volunteers and shifts for entrance staff', function () {
    $volunteer = Volunteer::factory()->create(['name' => 'Alice']);
    $ticket = Ticket::factory()->for($volunteer)->for($this->event)->create();
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Gate Watch']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();

    $data = $response->json();
    expect($data['event']['id'])->toBe($this->event->id)
        ->and($data['volunteers'])->toHaveCount(1)
        ->and($data['volunteers'][0]['name'])->toBe('Alice')
        ->and($data['volunteers'][0]['ticket']['jwt_token'])->toBeString()
        ->and($data['volunteers'][0]['shift_signups'])->toHaveCount(1)
        ->and($data['volunteers'][0]['shift_signups'][0]['shift']['volunteer_job']['name'])->toBe('Gate Watch');
});

it('returns HMAC keys (current + previous)', function () {
    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();

    $jwtKeyService = app(JwtKeyService::class);
    $data = $response->json();

    expect($data['keys']['current'])->toBe($jwtKeyService->deriveKey($this->event->id))
        ->and($data['keys']['previous'])->toBe($jwtKeyService->previousPeriodKey($this->event->id));
});

it('includes existing arrivals', function () {
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->for($volunteer)->for($this->event)->create();
    EventArrival::factory()->create([
        'ticket_id' => $ticket->id,
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'scanned_by' => $this->entranceStaff->id,
        'method' => ArrivalMethod::QrScan,
    ]);

    $response = $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id));

    $response->assertOk();
    expect($response->json('arrivals'))->toHaveCount(1);
});

it('returns 403 for volunteer admin', function () {
    $volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $this->event->id))
        ->assertForbidden();
});

it('redirects unauthenticated user', function () {
    $this->getJson(route('scanner.data', $this->event->id))
        ->assertUnauthorized();
});

it('returns 404 for event in wrong org', function () {
    $otherOrg = \App\Models\Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->getJson(route('scanner.data', $otherEvent->id))
        ->assertNotFound();
});
