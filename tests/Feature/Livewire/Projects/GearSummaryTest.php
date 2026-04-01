<?php

use App\Enums\StaffRole;
use App\Livewire\Projects\GearSummary;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('projects.gear-summary', $this->project))
        ->assertOk()
        ->assertSeeLivewire(GearSummary::class);
});

it('shows gear items with counts', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'T-Shirt']);
    $volunteer = Volunteer::factory()->for($this->project)->create();
    VolunteerGear::factory()->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $volunteer->id]);

    Livewire::actingAs($this->organizer)
        ->test(GearSummary::class, ['projectId' => $this->project->id])
        ->assertSee('T-Shirt')
        ->assertSee('1'); // total assigned
});

it('shows empty state when no gear items', function () {
    Livewire::actingAs($this->organizer)
        ->test(GearSummary::class, ['projectId' => $this->project->id])
        ->assertSee('Keine Gear-Artikel konfiguriert');
});

it('allows volunteer admin to view', function () {
    $va = User::factory()->create();
    $this->project->users()->attach($va, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($va)
        ->test(GearSummary::class, ['projectId' => $this->project->id])
        ->assertOk();
});

it('denies access to user with no project role', function () {
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(GearSummary::class, ['projectId' => $this->project->id])
        ->assertForbidden();
});

it('exports CSV as download', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);
    $volunteer = Volunteer::factory()->for($this->project)->create();
    VolunteerGear::factory()->create(['project_gear_item_id' => $item->id, 'volunteer_id' => $volunteer->id]);

    $response = Livewire::actingAs($this->organizer)
        ->test(GearSummary::class, ['projectId' => $this->project->id])
        ->call('exportCsv');

    $response->assertFileDownloaded("gear-summary-{$this->project->id}.csv");
});
