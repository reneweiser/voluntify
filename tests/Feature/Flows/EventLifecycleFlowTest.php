<?php

use App\Actions\CreateEvent;
use App\Actions\PublishEvent;
use App\Enums\EventStatus;
use App\Livewire\Events\EventShow;
use App\Livewire\Public\EventSignup;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
});

it('completes event lifecycle: create → add jobs → publish → public access → archive', function () {
    // Step 1: Create event
    $createAction = new CreateEvent;
    $event = $createAction->execute(
        organization: $this->org,
        name: 'Lifecycle Test Event',
        description: 'Testing the full lifecycle',
        location: 'Test Venue',
        startsAt: Carbon::parse('2026-09-01 10:00'),
        endsAt: Carbon::parse('2026-09-01 18:00'),
    );

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->public_token)->toBeString();

    // Step 2: Add jobs and shifts
    $job = VolunteerJob::factory()->for($event)->create(['name' => 'Setup Crew']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 10]);

    expect($event->volunteerJobs()->count())->toBe(1);

    // Step 3: Publish event
    $publishAction = new PublishEvent;
    $event = $publishAction->execute($event);

    expect($event->status)->toBe(EventStatus::Published);

    // Step 4: Public access works
    $this->get(route('events.public', $event->public_token))
        ->assertOk()
        ->assertSeeLivewire(EventSignup::class);

    // Step 5: Event shows correctly in admin
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $event->id])
        ->assertSee('Lifecycle Test Event')
        ->assertSee('Published')
        ->assertSee('Public signup link');

    // Step 6: Archive event
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $event->id])
        ->call('archiveEvent')
        ->assertDispatched('event-archived');

    expect($event->fresh()->status)->toBe(EventStatus::Archived);

    // Step 7: Public page no longer accessible after archive
    $this->get(route('events.public', $event->public_token))
        ->assertNotFound();
});
