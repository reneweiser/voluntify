<?php

use App\Enums\StaffRole;
use App\Jobs\SendAnnouncementJob;
use App\Livewire\Projects\AnnouncementComposer;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('renders for organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('projects.announcements', $this->project))
        ->assertOk()
        ->assertSeeLivewire(AnnouncementComposer::class);
});

it('denies access to non-organizer', function () {
    $va = User::factory()->create();
    $this->project->users()->attach($va, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($va)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id])
        ->assertForbidden();
});

it('shows correct recipient count', function () {
    Volunteer::factory()->for($this->project)->verified()->count(3)->create();
    Volunteer::factory()->for($this->project)->create(['email_verified_at' => null]);

    Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id])
        ->assertSee('3');
});

it('updates recipient count when event filter changes', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $v1 = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($v1)->for($shift)->create();

    // v2 has no signups for this event
    Volunteer::factory()->for($this->project)->verified()->create();

    $component = Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id]);

    // All verified volunteers initially
    expect($component->get('recipientCount'))->toBe(2);

    // Filter to event
    $component->set('selectedEventId', (string) $event->id);
    expect($component->get('recipientCount'))->toBe(1);
});

it('sends announcement immediately', function () {
    Queue::fake();

    Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id])
        ->set('subject', 'Test announcement')
        ->set('body', 'Hello everyone!')
        ->call('confirmSend')
        ->assertSet('showConfirmModal', true)
        ->call('send')
        ->assertDispatched('announcement-sent');

    expect(Announcement::count())->toBe(1);
    Queue::assertPushed(SendAnnouncementJob::class);
});

it('validates required fields', function () {
    Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id])
        ->call('confirmSend')
        ->assertHasErrors(['subject', 'body']);
});
