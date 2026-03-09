<?php

use App\Enums\EventStatus;
use App\Livewire\Public\EventGroupPage;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->group = EventGroup::factory()->for($this->org)->create([
        'name' => 'SKHC Festival',
        'description' => 'A multi-part festival',
    ]);
});

it('loads group by public_token and displays name and description', function () {
    Livewire::test(EventGroupPage::class, ['publicToken' => $this->group->public_token])
        ->assertSee('SKHC Festival')
        ->assertSee('A multi-part festival');
});

it('shows only published child events', function () {
    Event::factory()->for($this->org)->published()->create([
        'event_group_id' => $this->group->id,
        'name' => 'Published Event',
    ]);
    Event::factory()->for($this->org)->create([
        'event_group_id' => $this->group->id,
        'name' => 'Draft Event',
        'status' => EventStatus::Draft,
    ]);

    Livewire::test(EventGroupPage::class, ['publicToken' => $this->group->public_token])
        ->assertSee('Published Event')
        ->assertDontSee('Draft Event');
});

it('shows events ordered by starts_at', function () {
    Event::factory()->for($this->org)->published()->create([
        'event_group_id' => $this->group->id,
        'name' => 'Later Event',
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(4),
    ]);
    Event::factory()->for($this->org)->published()->create([
        'event_group_id' => $this->group->id,
        'name' => 'Earlier Event',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(4),
    ]);

    Livewire::test(EventGroupPage::class, ['publicToken' => $this->group->public_token])
        ->assertSeeInOrder(['Earlier Event', 'Later Event']);
});

it('returns 404 for invalid public_token', function () {
    $this->get(route('event-groups.public', 'nonexistent-token'))
        ->assertNotFound();
});

it('links each event to its public signup page', function () {
    $event = Event::factory()->for($this->org)->published()->create([
        'event_group_id' => $this->group->id,
        'name' => 'Linked Event',
    ]);

    Livewire::test(EventGroupPage::class, ['publicToken' => $this->group->public_token])
        ->assertSee(route('events.public', $event->public_token));
});
