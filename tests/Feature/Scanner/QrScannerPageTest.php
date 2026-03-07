<?php

use App\Enums\StaffRole;
use App\Livewire\Scanner\QrScanner;
use App\Models\Event;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->event = Event::factory()->for($this->org)->published()->create();
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertOk()
        ->assertSeeLivewire(QrScanner::class);
});

it('returns 403 for volunteer admin', function () {
    $this->actingAs($this->volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertForbidden();
});

it('renders for entrance staff', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertOk()
        ->assertSeeLivewire(QrScanner::class);
});

it('redirects unauthenticated users', function () {
    $this->get(route('scanner.scan', $this->event))
        ->assertRedirect(route('login'));
});

it('shows event name', function () {
    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class, ['eventId' => $this->event->id])
        ->assertSee($this->event->name);
});

it('returns 404 for event from other org', function () {
    $otherOrg = \App\Models\Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->published()->create();

    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $otherEvent))
        ->assertNotFound();
});

it('uses scanner layout', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertOk()
        ->assertDontSee('data-sidebar-marker', false)
        ->assertSee('data-scanner-layout', false);
});

it('has viewfinder container', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertOk()
        ->assertSee('data-scanner-viewfinder', false);
});

it('has manual lookup link', function () {
    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class, ['eventId' => $this->event->id])
        ->assertSee('Manual Lookup');
});

it('has exit button linking to scanner index', function () {
    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class, ['eventId' => $this->event->id])
        ->assertSeeHtml(route('scanner.index'));
});
