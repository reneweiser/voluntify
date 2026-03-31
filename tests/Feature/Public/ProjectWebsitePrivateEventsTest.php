<?php

use App\Enums\EventVisibility;
use App\Livewire\Public\ProjectWebsite;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
});

it('shows public published events on project website', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Public Event',
        'visibility' => EventVisibility::Public,
    ]);

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertSee('Public Event');
});

it('hides private published events from project website', function () {
    Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Private Workshop',
        'visibility' => EventVisibility::Private,
    ]);

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertDontSee('Private Workshop');
});

it('still allows direct access to private events via public token', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'visibility' => EventVisibility::Private,
    ]);

    $this->get(route('events.public', $event->public_token))
        ->assertOk();
});

it('defaults events to public visibility', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();

    expect($event->visibility)->toBe(EventVisibility::Public);
});
