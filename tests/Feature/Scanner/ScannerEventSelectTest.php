<?php

use App\Enums\StaffRole;
use App\Livewire\Scanner\ScannerEventSelect;
use App\Models\Event;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSeeLivewire(ScannerEventSelect::class);
});

it('renders for entrance staff', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSeeLivewire(ScannerEventSelect::class);
});

it('returns 403 for volunteer admin', function () {
    $this->actingAs($this->volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertForbidden();
});

it('redirects unauthenticated users', function () {
    $this->get(route('scanner.index'))
        ->assertRedirect(route('login'));
});

it('lists published org events', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertSee($event->name)
        ->assertSee('Start Scanning');
});

it('hides draft events', function () {
    $draft = Event::factory()->for($this->org)->create(['name' => 'Draft Event', 'status' => 'draft']);

    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertDontSee('Draft Event');
});

it('hides events from other orgs', function () {
    $otherOrg = \App\Models\Organization::factory()->create();
    Event::factory()->for($otherOrg)->published()->create(['name' => 'Other Org Event']);

    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertDontSee('Other Org Event');
});

it('shows empty state when no published events', function () {
    app()->instance(\App\Models\Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertSee('No published events');
});

it('uses app layout with sidebar', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertDontSee('data-scanner-layout', false);
});
