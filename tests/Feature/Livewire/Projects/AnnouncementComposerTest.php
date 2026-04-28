<?php

use App\Actions\SendAnnouncement;
use App\Enums\StaffRole;
use App\Jobs\SendAnnouncementJob;
use App\Livewire\Projects\AnnouncementComposer;
use App\Models\Announcement;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

it('counts token-backed recipients when event filter changes', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $tokenBackedVolunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'token-backed@example.com',
        'email_verified_at' => null,
    ]);
    ShiftSignup::factory()->for($tokenBackedVolunteer)->for($shift)->create();

    EmailVerificationToken::factory()->create([
        'project_id' => $this->project->id,
        'event_id' => $event->id,
        'email' => $tokenBackedVolunteer->email,
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subMinute(),
    ]);

    Volunteer::factory()->for($this->project)->create([
        'email_verified_at' => null,
        'email' => 'unverified@example.com',
    ]);

    $component = Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id]);

    $component->set('selectedEventId', (string) $event->id);

    expect($component->get('recipientCount'))->toBe(1);
});

it('counts token-backed recipients only once when multiple verified tokens exist', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'duplicate-token@example.com',
        'email_verified_at' => null,
    ]);
    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    EmailVerificationToken::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'event_id' => $event->id,
        'email' => $volunteer->email,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subMinute(),
    ]);

    $component = Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id]);

    $component->set('selectedEventId', (string) $event->id);

    expect($component->get('recipientCount'))->toBe(1);
});

it('keeps preview count and send recipient count in sync for token-backed volunteers', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'parity@example.com',
        'email_verified_at' => null,
    ]);
    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    EmailVerificationToken::factory()->create([
        'project_id' => $this->project->id,
        'event_id' => $event->id,
        'email' => $volunteer->email,
        'token_hash' => HashedToken::fromPlaintext(Str::random(64))->hash,
        'expires_at' => now()->addDay(),
        'verified_at' => now()->subMinute(),
    ]);

    $component = Livewire::actingAs($this->organizer)
        ->test(AnnouncementComposer::class, ['projectId' => $this->project->id]);

    $component
        ->set('selectedEventId', (string) $event->id)
        ->set('selectedJobId', (string) $job->id)
        ->set('selectedShiftId', (string) $shift->id);

    expect($component->get('recipientCount'))->toBe(1);

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'job_id' => $job->id,
        'shift_id' => $shift->id,
        'created_by' => $this->organizer->id,
    ]);

    app(SendAnnouncement::class)->execute($announcement);

    expect($announcement->fresh()->recipient_count)->toBe(1);
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
