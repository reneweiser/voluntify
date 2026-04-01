<?php

use App\Enums\HintLocation;
use App\Enums\StaffRole;
use App\Livewire\Projects\HintTextSettings;
use App\Models\HintText;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('renders for org organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('projects.hint-texts', $this->project))
        ->assertOk()
        ->assertSeeLivewire(HintTextSettings::class);
});

it('denies access to non-organizer', function () {
    $viewer = User::factory()->create();
    $this->project->users()->attach($viewer, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($viewer)
        ->get(route('projects.hint-texts', $this->project))
        ->assertForbidden();
});

it('shows all hint location cases', function () {
    Livewire::actingAs($this->organizer)
        ->test(HintTextSettings::class, ['projectId' => $this->project->id])
        ->assertSee('Anmeldung: E-Mail-Feld')
        ->assertSee('Anmeldung: Telefon-Feld')
        ->assertSee('Anmeldung: Zusammenfassung')
        ->assertSee('Portal: Willkommensbanner')
        ->assertSee('Portal: Schichten-Bereich')
        ->assertSee('Scanner: Willkommen');
});

it('can save a hint text', function () {
    Livewire::actingAs($this->organizer)
        ->test(HintTextSettings::class, ['projectId' => $this->project->id])
        ->call('startEditing', HintLocation::SignupEmail->value)
        ->assertSet('editingLocation', HintLocation::SignupEmail->value)
        ->set('editText', 'Bitte verwende deine private E-Mail.')
        ->call('saveHint')
        ->assertSet('editingLocation', null);

    $this->assertDatabaseHas('hint_texts', [
        'project_id' => $this->project->id,
        'location' => HintLocation::SignupEmail->value,
        'text' => 'Bitte verwende deine private E-Mail.',
        'enabled' => true,
    ]);
});

it('can toggle hint enabled/disabled', function () {
    $hint = HintText::factory()->for($this->project)->create([
        'location' => HintLocation::PortalTopBanner,
        'enabled' => true,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(HintTextSettings::class, ['projectId' => $this->project->id])
        ->call('toggleEnabled', HintLocation::PortalTopBanner->value);

    expect($hint->fresh()->enabled)->toBeFalse();
});

it('can delete a hint text', function () {
    HintText::factory()->for($this->project)->create([
        'location' => HintLocation::SignupPhone,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(HintTextSettings::class, ['projectId' => $this->project->id])
        ->call('deleteHint', HintLocation::SignupPhone->value);

    $this->assertDatabaseMissing('hint_texts', [
        'project_id' => $this->project->id,
        'location' => HintLocation::SignupPhone->value,
    ]);
});

it('shows configured hint text', function () {
    HintText::factory()->for($this->project)->create([
        'location' => HintLocation::SignupEmail,
        'text' => 'Custom hint for email field.',
        'enabled' => true,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(HintTextSettings::class, ['projectId' => $this->project->id])
        ->assertSee('Custom hint for email field.');
});
