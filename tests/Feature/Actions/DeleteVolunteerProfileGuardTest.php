<?php

use App\Actions\DeleteVolunteerProfile;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
    ]);
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    $this->action = new DeleteVolunteerProfile;
});

it('blocks deletion when volunteer has non-cancellable future shifts [#143]', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    expect(fn () => $this->action->execute($this->volunteer))
        ->toThrow(DomainException::class, 'Dein Profil kann gerade nicht gelöscht werden');
});

it('allows deletion when all shifts are completed [#143]', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subHours(4),
        'ends_at' => now()->subHours(2),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $this->action->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('allows deletion when all shifts are cancellable [#143]', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(2),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $this->action->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('allows deletion when volunteer has no active signups [#143]', function () {
    $this->action->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('allows deletion when only cancelled signups exist [#143]', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    $this->action->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('allows deletion when cancellation is disabled on project [#143]', function () {
    $this->project->update(['cancellation_enabled' => false, 'cancellation_cutoff_hours' => null]);

    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    $this->action->execute($this->volunteer);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('blocks deletion for shifts without defined times past cutoff [#143]', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => now()->toDateString(),
        'starts_at' => null,
        'ends_at' => null,
        'display_text' => 'Ganztägig',
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    expect(fn () => $this->action->execute($this->volunteer))
        ->toThrow(DomainException::class, 'Dein Profil kann gerade nicht gelöscht werden');
});
