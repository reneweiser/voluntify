<?php

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Jobs\SendRepublishNotificationJob;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();
    app()->instance(Organization::class, $this->org);
});

it('reverts a published event to draft', function () {
    $this->event->update(['status' => EventStatus::PublishedOpen]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('revertToDraft')
        ->assertDispatched('event-reverted-to-draft');

    expect($this->event->refresh()->status)->toBe(EventStatus::Draft);
});

it('shows republish modal for previously published event', function () {
    $this->event->update([
        'status' => EventStatus::Draft,
        'was_previously_published' => true,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('publishEvent')
        ->assertSet('showRepublishModal', true);

    // Event should still be Draft (not yet confirmed)
    expect($this->event->refresh()->status)->toBe(EventStatus::Draft);
});

it('publishes first-time event without modal', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('publishEvent')
        ->assertSet('showRepublishModal', false)
        ->assertDispatched('event-published');

    expect($this->event->refresh()->status)->toBe(EventStatus::PublishedOpen);
});

it('confirms republish with organizer note', function () {
    Queue::fake();

    $this->event->update([
        'status' => EventStatus::Draft,
        'was_previously_published' => true,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->set('republishNote', 'New location announced')
        ->call('confirmRepublish')
        ->assertSet('showRepublishModal', false)
        ->assertDispatched('event-published');

    expect($this->event->refresh()->status)->toBe(EventStatus::PublishedOpen);
    Queue::assertPushed(SendRepublishNotificationJob::class, function ($job) {
        return $job->organizerNote === 'New location announced';
    });
});

it('confirms republish without organizer note', function () {
    Queue::fake();

    $this->event->update([
        'status' => EventStatus::Draft,
        'was_previously_published' => true,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('confirmRepublish')
        ->assertDispatched('event-published');

    Queue::assertPushed(SendRepublishNotificationJob::class, function ($job) {
        return $job->organizerNote === null;
    });
});

it('shows the 7-day event deletion banner', function () {
    $this->event->update(['deletion_requested_at' => now()->subDays(2)]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee($this->event->deletion_requested_at->copy()->addDays(7)->format('d.m.Y'));
});
