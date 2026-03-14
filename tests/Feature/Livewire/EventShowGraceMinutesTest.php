<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->event = Event::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('saves grace minutes', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('attendanceGraceMinutes', 15)
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->attendance_grace_minutes)->toBe(15);
});

it('clears grace minutes when empty', function () {
    $this->event->update(['attendance_grace_minutes' => 10]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('attendanceGraceMinutes', '')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->attendance_grace_minutes)->toBeNull();
});

it('displays grace minutes in edit form', function () {
    $this->event->update(['attendance_grace_minutes' => 20]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSet('attendanceGraceMinutes', 20);
});

it('validates grace minutes max value', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('attendanceGraceMinutes', 999)
        ->call('saveEvent')
        ->assertHasErrors(['attendanceGraceMinutes']);
});
