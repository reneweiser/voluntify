<?php

use App\Enums\EventStatus;
use App\Livewire\Public\ProjectWebsite;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'name' => 'SKHC Festival',
        'description' => 'A multi-part festival',
    ]);
});

it('loads project by public_token and displays name and description', function () {
    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertSee('SKHC Festival')
        ->assertSee('A multi-part festival');
});

it('shows only published child events', function () {
    Event::factory()->for($this->org)->published()->create([
        'project_id' => $this->project->id,
        'name' => 'Published Event',
    ]);
    Event::factory()->for($this->org)->create([
        'project_id' => $this->project->id,
        'name' => 'Draft Event',
        'status' => EventStatus::Draft,
    ]);

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertSee('Published Event')
        ->assertDontSee('Draft Event');
});

it('shows events ordered by starts_at', function () {
    Event::factory()->for($this->org)->published()->create([
        'project_id' => $this->project->id,
        'name' => 'Later Event',
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(4),
    ]);
    Event::factory()->for($this->org)->published()->create([
        'project_id' => $this->project->id,
        'name' => 'Earlier Event',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
    ]);

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertSeeInOrder(['Earlier Event', 'Later Event']);
});

it('returns 404 for invalid public_token', function () {
    $this->get(route('projects.public', 'nonexistent-token'))
        ->assertNotFound();
});

it('links each event to its public signup page', function () {
    $event = Event::factory()->for($this->org)->published()->create([
        'project_id' => $this->project->id,
        'name' => 'Linked Event',
    ]);

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertSee(route('events.public', $event->public_token));
});
