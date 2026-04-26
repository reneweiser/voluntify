<?php

use App\Actions\DeleteVolunteerProfile;
use App\Enums\ActivityCategory;
use App\Enums\StaffRole;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\MagicLinkToken;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;
use App\Notifications\ProfileDeletionConfirmation;
use App\Notifications\VolunteerProfileDeletedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->organizer = User::factory()->create();
    $this->org->users()->attach($this->organizer, ['role' => StaffRole::Organizer]);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Setup Crew']);
    $this->futureShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create([
        'first_name' => 'Test',
        'last_name' => 'Volunteer',
    ]);
});

it('deletes volunteer record from database', function () {
    Notification::fake();

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('cascades deletion to all related records', function () {
    Notification::fake();

    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);
    $ticket = Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);
    $gearItem = ProjectGearItem::factory()->for($this->project)->create();
    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
    ]);
    $token = MagicLinkToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
    ]);

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    expect(ShiftSignup::find($signup->id))->toBeNull()
        ->and(Ticket::find($ticket->id))->toBeNull()
        ->and(VolunteerGear::find($gear->id))->toBeNull()
        ->and(MagicLinkToken::find($token->id))->toBeNull();
});

it('sends confirmation email before deletion', function () {
    Notification::fake();

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    Notification::assertSentTo($this->volunteer, ProfileDeletionConfirmation::class);
});

it('uses self-service wording for volunteer deletion confirmation', function () {
    Notification::fake();

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    Notification::assertSentTo($this->volunteer, ProfileDeletionConfirmation::class, function ($notification) {
        return $notification->deletedByName === null;
    });
});

it('notifies organizer after deletion', function () {
    Notification::fake();

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    Notification::assertSentTo($this->organizer, VolunteerProfileDeletedNotification::class);
});

it('does not affect other volunteers', function () {
    Notification::fake();

    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
    $otherSignup = ShiftSignup::factory()->create([
        'volunteer_id' => $otherVolunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    expect(Volunteer::find($otherVolunteer->id))->not->toBeNull()
        ->and(ShiftSignup::find($otherSignup->id))->not->toBeNull();
});

it('handles volunteer with no signups', function () {
    Notification::fake();

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('preserves volunteer name in activity log properties before deletion', function () {
    Notification::fake();

    $log = ActivityLog::factory()->create([
        'organization_id' => $this->org->id,
        'subject_type' => Volunteer::class,
        'subject_id' => $this->volunteer->id,
        'causer_type' => Volunteer::class,
        'causer_id' => $this->volunteer->id,
        'properties' => ['some' => 'data'],
    ]);

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    $log->refresh();
    expect($log->causer_id)->toBeNull()
        ->and($log->causer_type)->toBeNull()
        ->and($log->properties)->toHaveKey('deleted_volunteer_name', 'Test Volunteer')
        ->and($log->properties)->toHaveKey('some', 'data');
});

it('includes upcoming shifts in organizer notification', function () {
    Notification::fake();

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    app(DeleteVolunteerProfile::class)->execute($this->volunteer);

    Notification::assertSentTo($this->organizer, VolunteerProfileDeletedNotification::class, function ($notification) {
        return str_contains($notification->shiftSummary, 'Setup Crew');
    });
});

it('records organizer-initiated deletion context in notifications and activity logs', function () {
    Notification::fake();

    app(DeleteVolunteerProfile::class)->execute($this->volunteer, false, $this->organizer);

    Notification::assertSentTo($this->volunteer, ProfileDeletionConfirmation::class, function ($notification) {
        return $notification->deletedByName === $this->organizer->name;
    });

    Notification::assertSentTo($this->organizer, VolunteerProfileDeletedNotification::class, function ($notification) {
        return $notification->deletedByName === $this->organizer->name;
    });

    $log = ActivityLog::query()
        ->where('causer_type', User::class)
        ->where('causer_id', $this->organizer->id)
        ->where('subject_type', Volunteer::class)
        ->where('subject_id', $this->volunteer->id)
        ->where('action', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->category)->toBe(ActivityCategory::Volunteer)
        ->and($log->project_id)->toBe($this->project->id)
        ->and($log->properties)->toHaveKey('initiated_by_name', $this->organizer->name)
        ->and($log->properties)->toHaveKey('deleted_volunteer_name', 'Test Volunteer');
});
