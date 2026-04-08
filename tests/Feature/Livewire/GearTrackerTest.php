<?php

use App\Enums\StaffRole;
use App\Livewire\Events\GearTracker;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    app()->instance(Organization::class, $this->org);
});

it('marks gear as picked up via toggle', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('togglePickup', $gear->id)
        ->assertHasNoErrors();

    expect($gear->fresh()->isPickedUp())->toBeTrue();
});

it('creates gear on-demand when marking pickup for volunteer without record', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);
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

it('denies entrance staff access to gear tracker', function () {
    $entranceStaff = User::factory()->create();
    $this->org->users()->attach($entranceStaff, ['role' => StaffRole::EntranceStaff]);

    Livewire::actingAs($entranceStaff)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('increments Typ 2 gear pickup via recordQuantityPickup', function () {
    $item = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Tokens']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    $gear = VolunteerGear::factory()->withQuantity(3)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('recordQuantityPickup', $gear->id)
        ->assertHasNoErrors();

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1)
        ->and(VolunteerGearPickup::first()->quantity)->toBe(1);
});

it('prevents Typ 2 pickup when quantity exhausted', function () {
    $item = ProjectGearItem::factory()->quantity(1)->for($this->project)->create(['name' => 'Token']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    $gear = VolunteerGear::factory()->withQuantity(1)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('recordQuantityPickup', $gear->id)
        ->assertHasErrors(['gear']);

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1);
});

it('toggle no-ops for Typ 2 items', function () {
    $item = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Tokens']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    $gear = VolunteerGear::factory()->withQuantity(3)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('togglePickup', $gear->id)
        ->assertHasNoErrors();

    // Pickups should NOT be deleted — toggle is a no-op for Typ 2
    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1);
});

it('undoLastQuantityPickup removes only most recent pickup', function () {
    $item = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Tokens']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    $gear = VolunteerGear::factory()->withQuantity(3)->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1, 'picked_up_at' => now()->subMinute()]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1, 'picked_up_at' => now()]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->call('undoLastQuantityPickup', $gear->id)
        ->assertHasNoErrors();

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1);
});

it('renders volunteers with gear status', function () {
    $item = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Lanyard']);
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $item->id,
        'volunteer_id' => $volunteer->id,
    ]);

    // Make volunteer associated with this event via a shift signup
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->organizer)
        ->test(GearTracker::class, ['eventId' => $this->event->id])
        ->assertSee('Jane Doe')
        ->assertSee('Lanyard');
});
