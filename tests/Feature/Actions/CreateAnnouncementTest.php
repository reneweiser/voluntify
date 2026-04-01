<?php

use App\Actions\CreateAnnouncement;
use App\Jobs\SendAnnouncementJob;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
    $this->action = new CreateAnnouncement;
});

it('creates an immediate announcement and dispatches job', function () {
    Queue::fake();

    $announcement = $this->action->execute($this->project, [
        'subject' => 'Important update',
        'body' => 'Please read this.',
    ], $this->user);

    expect($announcement)->toBeInstanceOf(Announcement::class)
        ->and($announcement->subject)->toBe('Important update')
        ->and($announcement->project_id)->toBe($this->project->id)
        ->and($announcement->send_at)->toBeNull();

    Queue::assertPushed(SendAnnouncementJob::class, function ($job) use ($announcement) {
        return $job->announcement->id === $announcement->id;
    });
});

it('creates a scheduled announcement with delayed job', function () {
    Queue::fake();

    $sendAt = now()->addHours(3)->format('Y-m-d H:i:s');

    $announcement = $this->action->execute($this->project, [
        'subject' => 'Scheduled update',
        'body' => 'Coming soon.',
        'send_at' => $sendAt,
    ], $this->user);

    expect($announcement->send_at)->not->toBeNull();

    Queue::assertPushed(SendAnnouncementJob::class);
});

it('stores event and job filters', function () {
    Queue::fake();

    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();

    $announcement = $this->action->execute($this->project, [
        'subject' => 'Filtered',
        'body' => 'For specific group.',
        'event_id' => $event->id,
        'job_id' => $job->id,
    ], $this->user);

    expect($announcement->event_id)->toBe($event->id)
        ->and($announcement->job_id)->toBe($job->id);
});
