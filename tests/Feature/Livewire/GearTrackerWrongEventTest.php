<?php

use App\Enums\StaffRole;
use App\Livewire\Events\GearTracker;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    app()->instance(Organization::class, $this->org);
});

it('rejects assignAndPickup for volunteer from a different event', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    // Volunteer signed up for a different event in the same project
    $otherEvent = Event::factory()->for($this->org)->for($this->project)->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $otherShift->id]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('assignAndPickup', $item->id, $volunteer->id);
});

it('rejects assignAndPickup for volunteer not associated with any event', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Lanyard']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('assignAndPickup', $item->id, $volunteer->id);
});

it('accepts assignAndPickup for volunteer signed up for this event', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'T-Shirt']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('assignAndPickup', $item->id, $volunteer->id)
        ->assertHasNoErrors();

    $gear = VolunteerGear::where('project_gear_item_id', $item->id)
        ->where('volunteer_id', $volunteer->id)
        ->first();

    expect($gear)->not->toBeNull()
        ->and($gear->isPickedUp())->toBeTrue();
});

it('rejects togglePickup for gear from a different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherItem = ProjectGearItem::factory()->for($otherProject)->create();
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $otherItem->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('togglePickup', $gear->id);
});

it('rejects assignAndPickup for gear item from a different project', function () {
    $otherProject = Project::factory()->for($this->org)->create();
    $otherItem = ProjectGearItem::factory()->for($otherProject)->create();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('assignAndPickup', $otherItem->id, $volunteer->id);
});
