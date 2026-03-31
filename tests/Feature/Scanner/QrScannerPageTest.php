<?php

use App\Enums\StaffRole;
use App\Livewire\Scanner\QrScanner;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertOk()
        ->assertSeeLivewire(QrScanner::class);
});

it('renders for project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertOk()
        ->assertSeeLivewire(QrScanner::class);
});

it('denies non-member', function () {
    $outsider = User::factory()->create();

    // Non-member cannot resolve the org, so the event is not found
    $this->actingAs($outsider)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.scan', $this->event))
        ->assertNotFound();
});

it('redirects unauthenticated users', function () {
    $this->get(route('scanner.scan', $this->event))
        ->assertRedirect(route('login'));
});

it('shows event name', function () {
    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class, ['eventId' => $this->event->id])
        ->assertSee($this->event->name);
});

it('returns 404 for event from other org', function () {
    $otherOrg = Organization::factory()->create();
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
    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class, ['eventId' => $this->event->id])
        ->assertSee('Manual Lookup');
});

it('has exit button linking to scanner index', function () {
    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(QrScanner::class, ['eventId' => $this->event->id])
        ->assertSeeHtml(route('scanner.index'));
});
