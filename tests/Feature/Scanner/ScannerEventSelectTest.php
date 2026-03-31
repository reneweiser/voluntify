<?php

use App\Enums\StaffRole;
use App\Livewire\Scanner\ScannerEventSelect;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->project = Project::factory()->for($this->org)->create();
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSeeLivewire(ScannerEventSelect::class);
});

it('renders for project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertOk()
        ->assertSeeLivewire(ScannerEventSelect::class);
});

it('denies non-member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('scanner.index'))
        ->assertForbidden();
});

it('redirects unauthenticated users', function () {
    $this->get(route('scanner.index'))
        ->assertRedirect(route('login'));
});

it('lists published org events', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();

    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertSee($event->name)
        ->assertSee('Start Scanning');
});

it('hides draft events', function () {
    $draft = Event::factory()->for($this->org)->for($this->project)->create(['name' => 'Draft Event', 'status' => 'draft']);

    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertDontSee('Draft Event');
});

it('hides events from other orgs', function () {
    $otherOrg = Organization::factory()->create();
    Event::factory()->for($otherOrg)->published()->create(['name' => 'Other Org Event']);

    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($this->organizer)
        ->test(ScannerEventSelect::class)
        ->assertDontSee('Other Org Event');
});

it('shows empty state when no published events', function () {
    app()->instance(Organization::class, $this->org);

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

it('scopes events to assigned projects for project organizer', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'My Project Event']);
    $otherProject = Project::factory()->for($this->org)->create();
    Event::factory()->for($this->org)->for($otherProject)->published()->create(['name' => 'Other Project Event']);

    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    app()->instance(Organization::class, $this->org);

    Livewire::actingAs($projectOrganizer)
        ->test(ScannerEventSelect::class)
        ->assertSee('My Project Event')
        ->assertDontSee('Other Project Event');
});
