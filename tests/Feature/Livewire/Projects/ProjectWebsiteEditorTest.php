<?php

use App\Enums\StaffRole;
use App\Livewire\Projects\ProjectWebsiteEditor;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('projects.website-editor', $this->project))
        ->assertOk()
        ->assertSeeLivewire(ProjectWebsiteEditor::class);
});

it('denies access to non-organizer', function () {
    $va = User::factory()->create();
    $this->project->users()->attach($va, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($va)
        ->test(ProjectWebsiteEditor::class, ['projectId' => $this->project->id])
        ->assertForbidden();
});

it('can save website content', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectWebsiteEditor::class, ['projectId' => $this->project->id])
        ->set('websiteDescription', '# Welcome to our project')
        ->set('websiteContactInfo', 'info@example.com')
        ->call('saveWebsite')
        ->assertHasNoErrors()
        ->assertDispatched('website-saved');

    $this->project->refresh();
    expect($this->project->website_description)->toBe('# Welcome to our project')
        ->and($this->project->website_contact_info)->toBe('info@example.com');
});

it('can publish website', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectWebsiteEditor::class, ['projectId' => $this->project->id])
        ->set('websitePublished', true)
        ->call('saveWebsite')
        ->assertHasNoErrors();

    expect($this->project->refresh()->website_published)->toBeTrue();
});

it('can unpublish website', function () {
    $this->project->update(['website_published' => true]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectWebsiteEditor::class, ['projectId' => $this->project->id])
        ->set('websitePublished', false)
        ->call('saveWebsite')
        ->assertHasNoErrors();

    expect($this->project->refresh()->website_published)->toBeFalse();
});

it('validates description max length', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectWebsiteEditor::class, ['projectId' => $this->project->id])
        ->set('websiteDescription', str_repeat('a', 10001))
        ->call('saveWebsite')
        ->assertHasErrors(['websiteDescription']);
});

it('loads current values on mount', function () {
    $this->project->update([
        'website_description' => 'Existing description',
        'website_contact_info' => 'contact@test.com',
        'website_published' => true,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectWebsiteEditor::class, ['projectId' => $this->project->id])
        ->assertSet('websiteDescription', 'Existing description')
        ->assertSet('websiteContactInfo', 'contact@test.com')
        ->assertSet('websitePublished', true);
});
