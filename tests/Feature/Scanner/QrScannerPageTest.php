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
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSeeLivewire(QrScanner::class);
});

it('returns 403 for volunteer admin', function () {
    $this->actingAs($this->volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertForbidden();
});

it('renders for entrance staff', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSeeLivewire(QrScanner::class);
});

it('redirects unauthenticated users', function () {
    $this->get(route('scanner.index'))
        ->assertRedirect(route('login'));
});

it('lists events for selection', function () {
    $event2 = Event::factory()->for($this->org)->published()->create();

    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class)
        ->assertSee($this->event->name)
        ->assertSee($event2->name);
});

it('hides events from other orgs', function () {
    $otherOrg = \App\Models\Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->published()->create(['name' => 'Other Org Event']);

    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class)
        ->assertSee($this->event->name)
        ->assertDontSee('Other Org Event');
});

it('uses scanner layout', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertDontSee('data-sidebar-marker', false)
        ->assertSee('data-scanner-layout', false);
});

it('has viewfinder container', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSee('data-scanner-viewfinder', false);
});

it('has manual lookup link', function () {
    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class)
        ->assertSee('Manual Lookup');
});
