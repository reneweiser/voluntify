<?php

use App\Actions\UpdateVolunteerGearSelection;
use App\Enums\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Notifications\VolunteerEventUpdatedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->project = Project::factory()->for($this->organization)->create();
    $this->event = Event::factory()->for($this->organization)->for($this->project)->published()->create([
        'name' => 'Sommerfest',
    ]);
    $this->organizer = User::factory()->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);
});

it('updates a volunteer gear selection, logs the change, and sends the standard update email', function () {
    Notification::fake();

    $gearItem = ProjectGearItem::factory()->sized(['M', 'L', 'XL'])->for($this->project)->create([
        'name' => 'T-Shirt',
    ]);

    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
        'size' => 'L',
    ]);

    $updatedGear = app(UpdateVolunteerGearSelection::class)->execute(
        gear: $gear,
        event: $this->event,
        selection: 'XL',
        causer: $this->organizer,
    );

    expect($updatedGear->size)->toBe('XL');

    $log = ActivityLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->category)->toBe(ActivityCategory::Volunteer)
        ->and($log->action)->toBe('updated')
        ->and($log->subject_type)->toBe(Volunteer::class)
        ->and($log->subject_id)->toBe($this->volunteer->id)
        ->and($log->causer_id)->toBe($this->organizer->id)
        ->and($log->properties['gear_item_name'])->toBe('T-Shirt')
        ->and($log->properties['previous_selection'])->toBe('L')
        ->and($log->properties['new_selection'])->toBe('XL');

    Notification::assertSentTo($this->volunteer, VolunteerEventUpdatedNotification::class, function (VolunteerEventUpdatedNotification $notification) {
        return $notification->event->is($this->event)
            && str_contains($notification->organizerNote, 'T-Shirt')
            && str_contains($notification->organizerNote, 'L')
            && str_contains($notification->organizerNote, 'XL');
    });
});

it('allows setting a pending gear selection for the first time', function () {
    Notification::fake();

    $gearItem = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create([
        'name' => 'T-Shirt',
    ]);

    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
        'size' => null,
    ]);

    $updatedGear = app(UpdateVolunteerGearSelection::class)->execute(
        gear: $gear,
        event: $this->event,
        selection: 'M',
        causer: $this->organizer,
    );

    expect($updatedGear->size)->toBe('M');

    $log = ActivityLog::query()->latest('id')->first();

    expect($log->properties['previous_selection'])->toBe('Auswahl ausstehend')
        ->and($log->properties['new_selection'])->toBe('M');
});
